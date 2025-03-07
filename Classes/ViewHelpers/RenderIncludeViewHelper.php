<?php

declare(strict_types=1);

namespace AUS\SsiInclude\ViewHelpers;

use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\Event\RenderedEvent;
use AUS\SsiInclude\Register\LastRenderedContentRegister;
use AUS\SsiInclude\Utility\FilenameUtility;
use Closure;
use Exception;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;

use function assert;

#[Autoconfigure(public: true)]
class RenderIncludeViewHelper extends RenderViewHelper
{
    public const SSI_INCLUDE_DIR = '/typo3temp/tx_ssiinclude/';

    public const METHOD_SSI = 'ssi';

    public const METHOD_ESI = 'esi';

    public function __construct(
        private readonly Context $context,
        private readonly CacheManager $cacheManager,
        private readonly FilenameUtility $filenameUtility,
        private readonly LastRenderedContentRegister $lastRenderedContentRegister,
    ) {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Specifies the file name of the cache (without .html ending)', true);
        $this->registerArgument('cacheLifeTime', 'int', 'Specifies the lifetime in seconds (defaults to 300)', false, 300);
        $this->registerArgument('cacheTags', 'array', 'Tags to set that can clear with flushByTags', false, []);
    }

    /**
     * @deprecated can be removed if parent class does not have renderStatic anymore.
     */
    #[Override]
    public static function renderStatic(array $arguments, Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $isDisabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ssi_include', 'disabled');
        if ($isDisabled) {
            return parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);
        }

        $self = GeneralUtility::makeInstance(self::class);
        assert($self instanceof self);
        /** @noinspection PhpUnhandledExceptionInspection */
        return $self->renderNonStatic($arguments, $renderChildrenClosure, $renderingContext);
    }

    /**
     * @param array<string, mixed>|null $arguments
     * @throws Exception
     * @throws NoSuchCacheException
     * @throws AspectNotFoundException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function renderNonStatic(?array $arguments = null, ?Closure $renderChildrenClosure = null, ?RenderingContextInterface $renderingContext = null): string
    {
        $this->arguments = $arguments ?? $this->arguments;
        $this->renderingContext = $renderingContext ?? $this->renderingContext;
        $renderChildrenClosure ??= $this->buildRenderChildrenClosure();

        if ($this->isBackendUser()) {
            $content = parent::renderStatic($this->arguments, $renderChildrenClosure, $this->renderingContext);
            // Put the code to register to use in InternalSsiRedirectMiddleware if the site comes from page cache
            $this->lastRenderedContentRegister->set($content);
            return $content;
        }

        // Get frontend user groups for their group dependent include file
        $frontendUser = $this->context->getAspect('frontend.user');
        assert($frontendUser instanceof UserAspect);
        $groupString = '';
        if ($frontendUser->isLoggedIn()) {
            $groupString = '_' . implode('.', $frontendUser->getGroupIds());
        }

        // generate the cache filename
        $name = $this->validateName($this->arguments);
        $filename = $this->getSiteName() . '_' . $this->getLangauge() . '_' . $name . $groupString . '.html';

        // If the cache has not the proper entry, generate it
        $cache = $this->cacheManager->getCache('aus_ssi_include_cache');
        assert($cache instanceof SsiIncludeCacheFrontend);
        if (!$cache->has($filename)) {
            $html = parent::renderStatic($this->arguments, $renderChildrenClosure, $this->renderingContext);
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $renderedHtmlEvent = new RenderedEvent($html);
            $eventDispatcher->dispatch($renderedHtmlEvent);
            $html = $renderedHtmlEvent->getHtml();

            $cacheTags = ['tx_ssiinclude_' . $name, ...$this->arguments['cacheTags']];
            $cache->set($filename, $html, $cacheTags, $this->arguments['cacheLifeTime']);
        }

        // generate the variables needed for include commments
        $reqUrl = $this->filenameUtility->getReqUrl($filename);
        $method = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ssi_include', 'method');
        if ($method === self::METHOD_ESI) {
            return '<esi:include src="' . $reqUrl . '" />';
        }

        return '<!--# include wait="yes" virtual="' . $reqUrl . '" -->';
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

    /**
     * @throws AspectNotFoundException
     */
    protected function getLangauge(): int
    {
        return $this->context->getPropertyFromAspect('language', 'id');
    }

    /**
     * @throws AspectNotFoundException
     */
    protected function isBackendUser(): bool
    {
        return $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    protected function getSiteName(): string
    {
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getIdentifier();
    }
}
