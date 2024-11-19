<?php

declare(strict_types=1);

namespace AUS\SsiInclude\ViewHelpers;

use AUS\SsiInclude\Event\RenderedEvent;
use Closure;
use Exception;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;
use Webimpress\SafeWriter\FileWriter;

use function assert;

#[Autoconfigure(public: true)]
class RenderIncludeViewHelper extends RenderViewHelper
{
    public const SSI_INCLUDE_DIR = '/typo3temp/tx_ssiinclude/';

    public const METHOD_SSI = 'ssi';

    public const METHOD_ESI = 'esi';

    public function __construct(
        private readonly Context $context,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Specifies the file name of the cache (without .html ending)', true);
        $this->registerArgument('cacheLifeTime', 'int', 'Specifies the lifetime in seconds (defaults to 300)', false, 300);
    }

    /**
     * @deprecated can be removed if parent class dose not have renderStatic anymore.
     */
    #[Override]
    public static function renderStatic(array $arguments, Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $isDisabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ssi_include', 'disabled');
        if ($isDisabled) {
            return parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);
        }

        $self = GeneralUtility::makeInstance(self::class);
        assert($self instanceof self);
        return $self->renderNonStatic($arguments, $renderChildrenClosure, $renderingContext);
    }

    /**
     * @param array<string, mixed>|null $arguments
     */
    public function renderNonStatic(?array $arguments = null, ?Closure $renderChildrenClosure = null, ?RenderingContextInterface $renderingContext = null): string
    {
        $this->arguments = $arguments ?? $this->arguments;
        $this->renderingContext = $renderingContext ?? $this->renderingContext;
        $renderChildrenClosure ??= $this->buildRenderChildrenClosure();

        $name = $this->validateName($this->arguments);

        $filename = $this->getSiteName() . '_' . $this->getLangauge() . '_' . $name;
        $reverseProxyPrefix = '/' . trim($GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyPrefix'] ?? '', '/') . '/';
        $basePath = self::SSI_INCLUDE_DIR . $filename;
        $includePath = rtrim($reverseProxyPrefix, '/') . $basePath;
        $absolutePath = Environment::getPublicPath() . $basePath;
        if ($this->shouldRenderFile($absolutePath, $this->arguments['cacheLifeTime'])) {
            $html = parent::renderStatic($this->arguments, $renderChildrenClosure, $this->renderingContext);

            if ($this->isBackendUser()) {
                return $html;
            }

            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $renderedHtmlEvent = new RenderedEvent($html);
            $eventDispatcher->dispatch($renderedHtmlEvent);
            $html = $renderedHtmlEvent->getHtml();

            GeneralUtility::mkdir_deep(dirname($absolutePath));
            FileWriter::writeFile($absolutePath, $html);
            GeneralUtility::fixPermissions($absolutePath);
        }

        $method = $this->extensionConfiguration->get('ssi_include', 'method') ?: self::METHOD_SSI;
        $reqUrl = $includePath . '?ssi_include=' . $filename . '&originalRequestUri=' . urlencode((string)GeneralUtility::getIndpEnv('REQUEST_URI'));
        if ($method === self::METHOD_ESI) {
            return '<esi:include src="' . $reqUrl . '" />';
        }

        return '<!--# include wait="yes" virtual="' . $reqUrl . '" -->';
    }

    private function shouldRenderFile(string $absolutePath, int $cacheLifeTime): bool
    {
        if (!file_exists($absolutePath)) {
            return true;
        }

        if ((filemtime($absolutePath) + $cacheLifeTime) < time()) {
            return true;
        }

        return $this->isBackendUser();
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws Exception
     */
    private function validateName(array $arguments): string
    {
        if (ctype_alnum((string)$arguments['name'])) {
            return $arguments['name'];
        }

        throw new Exception(sprintf('Only Alphanumeric characters allowed got: "%s"', $arguments['name']));
    }

    protected function getLangauge(): int
    {
        return $this->context->getPropertyFromAspect('language', 'id');
    }

    protected function isBackendUser(): bool
    {
        return $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    protected function getSiteName(): string
    {
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getIdentifier();
    }
}
