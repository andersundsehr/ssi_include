<?php

declare(strict_types=1);

namespace AUS\SsiInclude\EventListener;

use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class AfterCacheableContentIsGeneratedEventListener
{
    /**
     * Disable page caching for logged in backend users to prevent caching of the SSI include content to the page.
     */
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        try {
            $isDisabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ssi_include', 'disabled');
            if ($isDisabled) {
                return;
            }

            $context = GeneralUtility::makeInstance(Context::class);
            assert($context instanceof Context);
            $backendUserContext = $context->getAspect('backend.user');
            if ($backendUserContext->isLoggedIn()) {
                $event->disableCaching();
            }
        } catch (Throwable) {
        }
    }
}
