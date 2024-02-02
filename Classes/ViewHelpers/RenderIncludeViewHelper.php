<?php

declare(strict_types=1);

namespace AUS\SsiInclude\ViewHelpers;

use AUS\SsiInclude\Event\RenderedEvent;
use Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;
use Webimpress\SafeWriter\FileWriter;

class RenderIncludeViewHelper extends RenderViewHelper
{
    public const SSI_INCLUDE_DIR = '/typo3temp/tx_ssiinclude/';

    protected static function getContext(): Context
    {
        return GeneralUtility::makeInstance(Context::class);
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Specifies the file name of the cache (without .html ending)', true);
        $this->registerArgument('cacheLifeTime', 'int', 'Specifies the lifetime in seconds (defaults to 300)', false, 300);
    }

    /**
     * @param array<string, mixed> $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @throws \Webimpress\SafeWriter\Exception\ExceptionInterface
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $name = static::validateName($arguments);

        $filename = static::getSiteName() . '_' . static::getLangauge() . '_' . $name;
        $basePath = self::SSI_INCLUDE_DIR . $filename;
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

            @mkdir(dirname($absolutePath), octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']), true);
            FileWriter::writeFile($absolutePath, $html);
            GeneralUtility::fixPermissions($absolutePath);
        }
        return '<!--# include wait="yes" virtual="' . $basePath . '?ssi_include=' . $filename . '" -->';
    }

    private static function shouldRenderFile(string $absolutePath, int $cacheLifeTime): bool
    {
        if (!file_exists($absolutePath)) {
            return true;
        }
        if ((filemtime($absolutePath) + $cacheLifeTime) < time()) {
            return true;
        }
        if (self::isBackendUser()) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return string
     * @throws Exception
     */
    private static function validateName(array $arguments): string
    {
        if (ctype_alnum($arguments['name'])) {
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
