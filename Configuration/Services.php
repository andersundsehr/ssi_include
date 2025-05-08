<?php

declare(strict_types=1);

use AUS\SsiInclude\EventListener\AfterCacheableContentIsGeneratedEventListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services
        ->load('AUS\\SsiInclude\\', __DIR__ . '/../Classes/*');

    // Tag some class as PSR-14 event listener.
    if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version()) >= 12000000) {
        $services->set(AfterCacheableContentIsGeneratedEventListener::class)
            ->tag('event.listener', [
                'identifier' => 'ssi_include/after-cacheable-content-is-generated-event-listener',
                'event' => AfterCacheableContentIsGeneratedEvent::class
            ]);
    }
};
