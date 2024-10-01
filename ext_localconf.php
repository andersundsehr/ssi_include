<?php

use AUS\SsiInclude\Cache\ClearCache;

if (!defined('TYPO3_COMPOSER_MODE')) {
    // include autoload if this is the TER version
    require __DIR__ . '/vendor/autoload.php';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = ClearCache::class . '->clearCache';
