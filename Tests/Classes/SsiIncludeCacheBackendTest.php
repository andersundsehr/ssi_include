<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Tests;

use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\Utility\FilenameUtility;
use Doctrine\DBAL\Exception;
use RuntimeException;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SsiIncludeCacheBackendTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['andersundsehr/ssi-include'];

    protected array $coreExtensionsToLoad = ['core', 'backend', 'frontend'];

    private string $ssiIncludeDir;

    /**
     * @throws NoSuchCacheException
     */
    protected function setUp(): void
    {
        $GLOBALS['EXEC_TIME'] = 1740476618;
        // needed to create cache tables and so on
        putenv('typo3DatabaseDriver=pdo_sqlite');
        // this will preconfigure $this->ssiIncludeDir
        $this->initializeCacheFramework();
        // setup always after putenv and the caching framework initialization
        parent::setUp();
        // now after setup public path is available for fulfil the variable
        $this->ssiIncludeDir = Environment::getPublicPath() . $this->ssiIncludeDir;
        GeneralUtility::mkdir_deep($this->ssiIncludeDir);
        GeneralUtility::fixPermissions($this->ssiIncludeDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $files = glob($this->ssiIncludeDir . '*.html');
        if (false === $files) {
            throw new RuntimeException('Failed to glob files in ' . $this->ssiIncludeDir);
        }

        array_map('unlink', $files);
        @rmdir($this->ssiIncludeDir);
    }

    /**
     * @throws NoSuchCacheException
     */
    private function initializeCacheFramework(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cacheManager->setCacheConfigurations([
            'aus_ssi_include_concrete_cache' => [
                'frontend' => VariableFrontend::class,
                'backend' => Typo3DatabaseBackend::class,
            ],
            'aus_ssi_include_cache' => [
                'frontend' => SsiIncludeCacheFrontend::class,
                'backend' => SsiIncludeCacheBackend::class,
            ]
        ]);
        $backend = $cacheManager->getCache('aus_ssi_include_cache')->getBackend();
        assert($backend instanceof SsiIncludeCacheBackend);
        $this->ssiIncludeDir = $backend->getSsiIncludeDir();
    }

    /**
     * @test
     * @throws Exception
     */
    public function cacheTableExists(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('cache_aus_ssi_include_concrete_cache');
        $query = $connection->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name='cache_aus_ssi_include_concrete_cache'");
        $result = $query->fetchAssociative();

        self::assertNotEmpty($result, 'The cache_ssi_cache table was not created.');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function cacheEntryIsStoredAndRetrievedSuccessfully(): void
    {
        $entryIdentifier = 'test_entry.html';
        $data = '<h1>Cached Content</h1>';

        // Store cache entry
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cache->set($entryIdentifier, $data);

        // Retrieve cache entry
        $cachedData = $cache->get($entryIdentifier);
        self::assertIsString($cachedData);
        self::assertSame($data, $cachedData);

        // Check if file exists
        $absoluteFilename = GeneralUtility::makeInstance(FilenameUtility::class)->getAbsoluteFilename($entryIdentifier);
        self::assertFileExists($absoluteFilename);
        self::assertStringEqualsFile($absoluteFilename, '<h1>Cached Content</h1>');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function cacheEntryIsRemovedSuccessfully(): void
    {
        $entryIdentifier = 'test_entry.html';
        $data = '<h1>Cached Content</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cache->set($entryIdentifier, $data);
        self::assertTrue($cache->has($entryIdentifier));

        $cache->remove($entryIdentifier);
        self::assertFalse($cache->has($entryIdentifier));

        // Ensure file is deleted
        $absoluteFilename = GeneralUtility::makeInstance(FilenameUtility::class)->getAbsoluteFilename($entryIdentifier);
        self::assertFileDoesNotExist($absoluteFilename);
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function flushRemovesAllCacheEntries(): void
    {
        $data1 = '<h1>Content 1</h1>';
        $data2 = '<h1>Content 2</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');

        $cache->set('entry1.html', $data1);
        $cache->set('entry2.html', $data2);

        self::assertTrue($cache->has('entry1.html'));
        self::assertTrue($cache->has('entry2.html'));

        $cache->flush();

        self::assertFalse($cache->has('entry1.html'));
        self::assertFalse($cache->has('entry2.html'));

        // Ensure files are deleted
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'entry1.html');
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'entry2.html');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function flushByTagRemovesOnlyMatchingEntries(): void
    {
        $data1 = '<h1>Content 1</h1>';
        $data2 = '<h1>Content 2</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');

        $cache->set('entry1.html', $data1, ['tag1']);
        $cache->set('entry2.html', $data2, ['tag2']);

        self::assertTrue($cache->has('entry1.html'));
        self::assertTrue($cache->has('entry2.html'));

        // Flush by tag
        $cache->flushByTag('tag1');

        self::assertFalse($cache->has('entry1.html'));
        self::assertTrue($cache->has('entry2.html'));

        // Ensure only entry1 file is deleted
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'entry1.html');
        self::assertFileExists($this->ssiIncludeDir . 'entry2.html');
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function garbageCollectionRemovesOrphanedFiles(): void
    {
        $orphanedFile = $this->ssiIncludeDir . 'orphaned.html';
        file_put_contents($orphanedFile, '<h1>Orphaned Content</h1>');

        // Ensure file exists before garbage collection
        self::assertFileExists($orphanedFile);

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cache->collectGarbage();

        // The orphaned file should be deleted
        self::assertFileDoesNotExist($orphanedFile);
    }

    /**
     * @test
     * @throws NoSuchCacheException
     */
    public function garbageCollectionRemovesOutdatedFiles(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        assert($cacheManager instanceof CacheManager);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');

        $cache->set('outdated.html', '<h1>Outdated Content</h1>', [], 1);
        $GLOBALS['EXEC_TIME'] += 2;
        $cache->collectGarbage();
        $GLOBALS['EXEC_TIME'] -= 2;

        // The orphaned file should be deleted
        $data = $cache->get('outdated.html');
        self::assertFalse($data);
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'outdated.html');
    }
}
