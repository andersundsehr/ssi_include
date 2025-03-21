<?php

declare(strict_types=1);

namespace AUS\SsiInclude\EventListener;

use AUS\SsiInclude\Utility\IsCacheableUtility;
use Throwable;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

class AfterCacheableContentIsGeneratedEventListener
{
    /**
     * Disable page caching for logged in backend users to prevent caching of the SSI include content to the page.
     */
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        try {
            if (!(new IsCacheableUtility())->usePageCache(null, true)) {
                $event->disableCaching();
            }
        } catch (Throwable) {
        }
    }
}
