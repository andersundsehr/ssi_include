<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Cache\Backend;

use AUS\SsiInclude\Utility\FilenameUtility;
use InvalidArgumentException;
use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Cache\Backend\BackendInterface;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webimpress\SafeWriter\Exception\ExceptionInterface;
use Webimpress\SafeWriter\FileWriter;

class SsiIncludeCacheBackend extends AbstractBackend implements TaggableBackendInterface
{
    private readonly TaggableBackendInterface $concrete;

    private string $concreteCache = 'aus_ssi_include_concrete_cache';

    private readonly FilenameUtility $filenameUtility;

    /**
     * path where the include files are saved for use with the webserver
     */
    private string $ssiIncludeDir = '/typo3temp/tx_ssiinclude/';

    private bool $storeData = true;

    /**
     * @inheritdoc
     * @param array<string, mixed> $options
     * @throws NoSuchCacheException
     */
    public function __construct($context, array $options = [])
    {
        parent::__construct($context, $options);

        $this->filenameUtility = GeneralUtility::makeInstance(FilenameUtility::class);

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $concrete = $cacheManager->getCache($this->concreteCache)->getBackend();
        assert($concrete instanceof BackendInterface);
        assert($concrete instanceof TaggableBackendInterface);
        $this->concrete = $concrete;
    }

    /**
     * The cache identifier of the concrete cache which is used to save
     * the data. If storeData is false, it also creates an empty cache entry
     * to include with caching framework and have the identifier tied to the cache tags and lifetime
     */
    public function setConcreteCache(string $concreteCache): void
    {
        $this->concreteCache = $concreteCache;
    }

    /**
     * public accessible directory where the file is saved
     */
    public function setSsiIncludeDir(string $ssiIncludeDir): void
    {
        $this->ssiIncludeDir = '/' . trim($ssiIncludeDir, '/') . '/';
    }

    /**
     * if set, behaves like other cache backends, but if you do not need to receive the stored data
     * you can set this to false and save some space
     */
    public function setStoreData(bool $storeData): void
    {
        $this->storeData = $storeData;
    }

    public function getSsiIncludeDir(): string
    {
        return $this->ssiIncludeDir;
    }

    /**
     * @inheritdoc
     */
    public function setCache(FrontendInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     * @param array<string> $tags
     * @throws ExceptionInterface
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null): void
    {
        if (!is_string($data)) {
            throw new InvalidArgumentException('Data must be a string', 1616420133);
        }

        $absolutePath = $this->filenameUtility->getAbsoluteFilename($entryIdentifier);

        GeneralUtility::mkdir_deep(dirname($absolutePath));
        GeneralUtility::fixPermissions(dirname($absolutePath));

        FileWriter::writeFile($absolutePath, $data);
        GeneralUtility::fixPermissions($absolutePath);

        $this->concrete->set($entryIdentifier, $this->storeData ? $data : '', $tags, $lifetime);
    }

    /**
     * @inheritdoc
     */
    public function get($entryIdentifier): false|string
    {
        return $this->concrete->get($entryIdentifier);
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function has($entryIdentifier): bool
    {
        $data = $this->concrete->has($entryIdentifier);
        if (!$data) {
            return false;
        }

        $absoluteFile = $this->filenameUtility->getAbsoluteFilename($entryIdentifier);
        return file_exists($absoluteFile);
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function remove($entryIdentifier): bool
    {
        $absoluteFile = $this->filenameUtility->getAbsoluteFilename($entryIdentifier);
        if (file_exists($absoluteFile)) {
            unlink($absoluteFile);
        }

        return $this->concrete->remove($entryIdentifier);
    }

    /**
     * @inheritdoc
     */
    public function flush(): void
    {
        foreach ($this->getSsiIncludeDirFiles() as $file) {
            unlink($file);
        }

        $this->concrete->flush();
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function collectGarbage(): void
    {
        // remove outdated things
        $this->concrete->collectGarbage();

        // get all files, and the file that has no entry remove them
        $files = $this->getSsiIncludeDirFiles();
        foreach ($files as $absoluteFilename) {
            $file = basename($absoluteFilename);
            if (!$this->has($file)) {
                $absoluteFilename = $this->filenameUtility->getAbsoluteFilename($file);
                unlink($absoluteFilename);
            }
        }
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function flushByTag($tag): void
    {
        $entryIdentifiers = $this->findIdentifiersByTag($tag);
        foreach ($entryIdentifiers as $entryIdentifier) {
            $this->remove($entryIdentifier);
        }
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function flushByTags(array $tags): void
    {
        $this->concrete->flushByTags($tags);
        foreach ($tags as $tag) {
            $this->flushByTag($tag);
        }
    }

    /**
     * @inheritdoc
     * @param string $tag
     * @return list<string>
     */
    public function findIdentifiersByTag($tag): array
    {
        return $this->concrete->findIdentifiersByTag($tag);
    }

    /**
     * @return list<string>
     */
    private function getSsiIncludeDirFiles(): array
    {
        $publicIncludeDir = Environment::getPublicPath() . $this->ssiIncludeDir;
        return glob($publicIncludeDir . '*.html') ?: [];
    }
}
