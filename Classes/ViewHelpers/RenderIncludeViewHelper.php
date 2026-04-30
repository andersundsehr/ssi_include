<?php

declare(strict_types=1);

namespace AUS\SsiInclude\ViewHelpers;

use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\Event\RenderedEvent;
use AUS\SsiInclude\Register\LastRenderedContentRegister;
use AUS\SsiInclude\Utility\FilenameUtility;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;
use Webimpress\SafeWriter\Exception\ExceptionInterface;

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
        $this->registerArgument('cacheLifeTime', 'int|null', 'Specifies the lifetime in seconds');
        $this->registerArgument('cacheTags', 'array', 'Tags to set that can clear with flushByTags', false, []);
    }

    /**
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws NoSuchCacheException
     * @throws \TYPO3\CMS\Core\Cache\Exception
     * @throws InvalidDataException
     * @throws AspectNotFoundException
     * @throws ExceptionInterface
     */
    public function render(): string
    {
        // generate the cache filename
        $name = $this->validateName($this->arguments);

        if ($this->isBackendUser()) {
            $content = parent::render();
            // Put the code to register to use in InternalSsiRedirectMiddleware if the site comes from page cache
            assert(is_string($content));
            $this->lastRenderedContentRegister->set($name, $content);
            return $content;
        }

        // Get frontend user groups for their group dependent include file
        $frontendUser = $this->context->getAspect('frontend.user');
        /** @phpstan-ignore instanceof.alwaysTrue, function.alreadyNarrowedType */
        assert($frontendUser instanceof UserAspect);
        $groupString = '';
        if ($frontendUser->isLoggedIn()) {
            $groupString = '_' . implode('-', $frontendUser->getGroupIds());
        }

        $filename = $this->getSiteName() . '_' . $this->getLanguage() . '_' . $name . $groupString . '.html';

        // If the cache has not the proper entry, generate it
        $cache = $this->cacheManager->getCache('aus_ssi_include_cache');
        assert($cache instanceof SsiIncludeCacheFrontend);

        if (!$cache->has($filename)) {
            $html = parent::render();
            assert(is_string($html));
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $renderedHtmlEvent = new RenderedEvent($html);
            $eventDispatcher->dispatch($renderedHtmlEvent);
            $html = $renderedHtmlEvent->getHtml();

            /** @var list<string> $cacheTags */
            $cacheTags = $this->arguments['cacheTags'] ?? [];
            $cacheTags[] = 'tx_ssiinclude_' . $name;

            $cacheLifeTime = $this->arguments['cacheLifeTime'];
            assert(is_int($cacheLifeTime) || null === $cacheLifeTime);
            $cache->set($filename, $html, $cacheTags, $cacheLifeTime);
            $this->lastRenderedContentRegister->set($name, $html);
        }

        // generate the variables needed for include comments
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
        assert(is_string($arguments['name']));
        if (ctype_alnum($arguments['name'])) {
            return $arguments['name'];
        }

        throw new Exception(sprintf('Only Alphanumeric characters allowed got: "%s"', $arguments['name']), 9135532467);
    }

    /**
     * @throws AspectNotFoundException
     */
    protected function getLanguage(): int
    {
        $language = $this->context->getPropertyFromAspect('language', 'id');
        assert(is_int($language));
        return $language;
    }

    /**
     * @throws AspectNotFoundException
     */
    protected function isBackendUser(): bool
    {
        return (bool)$this->context->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    protected function getSiteName(): string
    {
        /** @phpstan-ignore method.nonObject */
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getIdentifier();
    }
}
