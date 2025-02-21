<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Utility;

use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FilenameUtility
{
    private string $ssiIncludeDir = '';

    /**
     * @throws NoSuchCacheException
     */
    private function getSsiIncludeDir(): string
    {
        if ($this->ssiIncludeDir !== '') {
            return $this->ssiIncludeDir;
        }

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cacheBackend = $cache->getBackend();
        assert($cacheBackend instanceof SsiIncludeCacheBackend);
        $this->ssiIncludeDir = $cacheBackend->getSsiIncludeDir();
        return $this->ssiIncludeDir;
    }

    /**
     * @throws NoSuchCacheException
     */
    public function getAbsoluteFilename(string $filename): string
    {
        $basePath = $this->getSsiIncludeDir() . $filename;
        return Environment::getPublicPath() . $basePath;
    }

    /**
     * @throws NoSuchCacheException
     */
    public function getReqUrl(string $filename): string
    {
        $reverseProxyPrefix = '/' . trim($GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyPrefix'] ?? '', '/') . '/';
        $includePath = rtrim($reverseProxyPrefix, '/') . $this->getSsiIncludeDir();
        return  $includePath . $filename . '?ssi_include=' . $filename . '&originalRequestUri=' . urlencode((string)GeneralUtility::getIndpEnv('REQUEST_URI'));
    }
}
