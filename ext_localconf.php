<?php

use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use AUS\SsiInclude\Cache\ClearCache;
use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

if (!defined('TYPO3_COMPOSER_MODE')) {
    // include autoload if this is the TER version
    require __DIR__ . '/vendor/autoload.php';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = ClearCache::class . '->clearCache';

// we might want to have a default concrete cache if not already defined in another way
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache']['options']['concreteCache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_concrete_cache'] = [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
    ];
}

// TODO nochmal testen wie die ergebnisse sind jenachdem was ich im projekt hinterlege
// define the main cache with the possibility to have a partial configuration already
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] = array_merge([
    'frontend' => SsiIncludeCacheFrontend::class,
    'backend' => SsiIncludeCacheBackend::class,
], $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] ?? []);
