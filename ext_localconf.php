<?php

use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use AUS\SsiInclude\Cache\ClearCache;
use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\EventListener\AfterCacheableContentIsGeneratedEventListener;
use AUS\SsiInclude\Utility\IsCacheableUtility;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

if (!defined('TYPO3_COMPOSER_MODE')) {
    // include autoload if this is the TER version
    require __DIR__ . '/vendor/autoload.php';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = ClearCache::class . '->clearCache';

// define the main cache with the possibility to have a partial configuration already
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] = array_merge([
    'frontend' => SsiIncludeCacheFrontend::class,
    'backend' => SsiIncludeCacheBackend::class,
], $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] ?? []);

if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version()) < 12000000) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['usePageCache'][] = IsCacheableUtility::class;
}
