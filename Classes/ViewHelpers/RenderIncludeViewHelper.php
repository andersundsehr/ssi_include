<?php

declare(strict_types=1);

namespace AUS\SsiInclude\ViewHelpers;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Webimpress\SafeWriter\Exception\ExceptionInterface;
use Closure;
use AUS\SsiInclude\Event\RenderedEvent;
use Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;
use Webimpress\SafeWriter\FileWriter;

class RenderIncludeViewHelper extends RenderViewHelper
{
    public const SSI_INCLUDE_DIR = '/typo3temp/tx_ssiinclude/';

    public const METHOD_SSI = 'ssi';

    public const METHOD_ESI = 'esi';

    protected static function getContext(): Context
    {
        return GeneralUtility::makeInstance(Context::class);
    }

    protected static function getExtensionConfiguration(): ExtensionConfiguration
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Specifies the file name of the cache (without .html ending)', true);
        $this->registerArgument('cacheLifeTime', 'int', 'Specifies the lifetime in seconds (defaults to 300)', false, 300);
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws Exception
     * @throws ExceptionInterface
     */
    public static function renderStatic(array $arguments, Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $name = self::validateName($arguments);

        $filename = static::getSiteName() . '_' . static::getLangauge() . '_' . $name;
        $reverseProxyPrefix = '/' . trim($GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyPrefix'] ?? '', '/') . '/';
        $basePath = self::SSI_INCLUDE_DIR . $filename;
        $includePath = rtrim($reverseProxyPrefix, '/') . $basePath;
        $absolutePath = Environment::getPublicPath() . $basePath;
        if (self::shouldRenderFile($absolutePath, $arguments['cacheLifeTime'])) {
            $html = parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);
            if (self::isBackendUser()) {
                return $html;
            }

            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $renderedHtmlEvent = new RenderedEvent($html);
            $eventDispatcher->dispatch($renderedHtmlEvent);
            $html = $renderedHtmlEvent->getHtml();

            @mkdir(dirname($absolutePath), (int)octdec((string)$GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']), true);
            FileWriter::writeFile($absolutePath, $html);
            GeneralUtility::fixPermissions($absolutePath);
        }

        $method = self::getExtensionConfiguration()->get('ssi_include', 'method') ?: self::METHOD_SSI;
        $reqUrl = $includePath . '?ssi_include=' . $filename . '&originalRequestUri=' . urlencode((string)GeneralUtility::getIndpEnv('REQUEST_URI'));
        if ($method === self::METHOD_ESI) {
            return '<esi:include src="' . $reqUrl . '" />';
        }

        return '<!--# include wait="yes" virtual="' . $reqUrl . '" -->';
    }

    private static function shouldRenderFile(string $absolutePath, int $cacheLifeTime): bool
    {
        if (!file_exists($absolutePath)) {
            return true;
        }

        if ((filemtime($absolutePath) + $cacheLifeTime) < time()) {
            return true;
        }

        return self::isBackendUser();
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws Exception
     */
    private static function validateName(array $arguments): string
    {
        if (ctype_alnum((string)$arguments['name'])) {
            return $arguments['name'];
        }

        throw new Exception(sprintf('Only Alphanumeric characters allowed got: "%s"', $arguments['name']));
    }

    protected static function getLangauge(): int
    {
        return self::getContext()->getPropertyFromAspect('language', 'id');
    }

    protected static function isBackendUser(): bool
    {
        return self::getContext()->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    protected static function getSiteName(): string
    {
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getIdentifier();
    }
}
