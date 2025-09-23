<?php

use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\Utility\IsCacheableUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

if (!defined('TYPO3_COMPOSER_MODE')) {
    // include autoload if this is the TER version
    require __DIR__ . '/vendor/autoload.php';
}

// define the main cache with the possibility to have a partial configuration already
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] = array_merge([
    'frontend' => SsiIncludeCacheFrontend::class,
    'backend' => SsiIncludeCacheBackend::class,
    'groups' => ['pages'],
], $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aus_ssi_include_cache'] ?? []);

if (VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version()) < 12000000) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['usePageCache'][] = IsCacheableUtility::class;
}
