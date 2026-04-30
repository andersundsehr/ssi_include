<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Tests;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use AUS\SsiInclude\Cache\Backend\SsiIncludeCacheBackend;
use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use AUS\SsiInclude\Utility\FilenameUtility;
use Doctrine\DBAL\Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
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
        $this->copySiteConfiguration();
        // now after setup public path is available for fulfil the variable
        $this->ssiIncludeDir = Environment::getPublicPath() . $this->ssiIncludeDir;
        GeneralUtility::mkdir_deep($this->ssiIncludeDir);
        GeneralUtility::fixPermissions($this->ssiIncludeDir);
    }

    private function copySiteConfiguration(): void
    {
        $sourcePath = __DIR__ . '/../Fixtures/Sites/';
        // there the SiteConfiguration::getAllSiteConfigurationFromFiles it looks for our sites if it changes, check the path there
        $destinationPath = $this->instancePath . '/typo3conf/sites/default/';

        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        (new Filesystem())->copy(
            $sourcePath . 'config.yaml',
            $destinationPath . 'config.yaml',
            true
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->flushCaches();
    }

    /**
     * @throws NoSuchCacheException
     */
    private function initializeCacheFramework(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->setCacheConfigurations([
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
     * @throws Exception
     */
    #[Test]
    public function cacheTableExists(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('cache_aus_ssi_include_cache');
        $query = $connection->executeQuery("SELECT name FROM sqlite_master WHERE type='table' AND name='cache_aus_ssi_include_cache'");
        $result = $query->fetchAssociative();

        self::assertNotEmpty($result, 'The cache_aus_ssi_include_cache table was not created.');
    }

    /**
     * @throws NoSuchCacheException
     */
    #[Test]
    public function cacheEntryIsStoredAndRetrievedSuccessfully(): void
    {
        $entryIdentifier = 'test_entry.html';
        $data = '<h1>Cached Content</h1>';

        // Store cache entry
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
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
     * @throws NoSuchCacheException
     */
    #[Test]
    public function cacheEntryIsRemovedSuccessfully(): void
    {
        $entryIdentifier = 'test_entry.html';
        $data = '<h1>Cached Content</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
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
     * @throws NoSuchCacheException
     */
    #[Test]
    public function flushRemovesAllCacheEntries(): void
    {
        $data1 = '<h1>Content 1</h1>';
        $data2 = '<h1>Content 2</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
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
     * @throws NoSuchCacheException
     */
    #[Test]
    public function flushByTagRemovesOnlyMatchingEntries(): void
    {
        $data1 = '<h1>Content 1</h1>';
        $data2 = '<h1>Content 2</h1>';

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
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
     * @throws NoSuchCacheException
     */
    #[Test]
    public function garbageCollectionRemovesOrphanedFiles(): void
    {
        $orphanedFile = $this->ssiIncludeDir . 'orphaned.html';
        file_put_contents($orphanedFile, '<h1>Orphaned Content</h1>');

        // Ensure file exists before garbage collection
        self::assertFileExists($orphanedFile);

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cache->collectGarbage();

        // The orphaned file should be deleted
        self::assertFileDoesNotExist($orphanedFile);
    }

    /**
     * @throws NoSuchCacheException
     */
    #[Test]
    public function garbageCollectionRemovesOutdatedFiles(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');

        $cache->set('outdated.html', '<h1>Outdated Content</h1>', [], 1);
        assert(is_int($GLOBALS['EXEC_TIME']));
        $GLOBALS['EXEC_TIME'] += 2;
        $cache->collectGarbage();
        $GLOBALS['EXEC_TIME'] -= 2;

        // The orphaned file should be deleted
        $data = $cache->get('outdated.html');
        self::assertFalse($data);
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'outdated.html');
    }

    #[Test]
    public function cacheFlushEventRemovesAllFiles(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        $cache->set('cacheFlushEventRemovesAllFiles1.html', 'test', ['tag1']);
        self::assertTrue($cache->has('cacheFlushEventRemovesAllFiles1.html'));
        self::assertFileExists($this->ssiIncludeDir . 'cacheFlushEventRemovesAllFiles1.html');

        $orphanedFile = $this->ssiIncludeDir . 'cacheFlushEventRemovesAllFiles2.html';
        file_put_contents($orphanedFile, '<h1>Orphaned Content</h1>');
        self::assertFileExists($orphanedFile);

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $event = new CacheFlushEvent(['pages']);
        $eventDispatcher->dispatch($event);

        self::assertFalse($cache->has('cacheFlushEventRemovesAllFiles1.html'));
        self::assertFileDoesNotExist($this->ssiIncludeDir . 'cacheFlushEventRemovesAllFiles1.html');
        self::assertFileDoesNotExist($orphanedFile);
    }

    /**
     * @throws NoSuchCacheException
     */
    #[Test]
    public function noCacheFileCreatedWhenBackendUserIsLoggedIn(): void
    {
        // Import a page tree with a test page
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        // Set extension configuration
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ssi_include']['disabled'] = '0';
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ssi_include']['method'] = 'ssi';

        // Set up TypoScript template for the test page
        $this->setUpFrontendRootPage(1, [
            'EXT:ssi_include/Tests/Fixtures/TypoScript/test_page.typoscript'
        ]);

        // Get expected SSI include filename
        $expectedFilename = 'default_0_testinclude.html'; // site_language_name_usergroups.html format

        // Verify file doesn't exist before request
        $absoluteFilename = GeneralUtility::makeInstance(FilenameUtility::class)->getAbsoluteFilename($expectedFilename);
        if (file_exists($absoluteFilename)) {
            unlink($absoluteFilename);
        }

        self::assertFileDoesNotExist($absoluteFilename);

        // Create a ServerRequest with the URI
        $request = (new InternalRequest());
        $request = $request->withMethod('GET');

        $context = (new InternalRequestContext())->withBackendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $context);

        // Verify the response was successful
        self::assertEquals(200, $response->getStatusCode());

        $responseBody = (string)$response->getBody();

        // When backend user is logged in, content should be rendered directly
        // and NO SSI include file should be created
        self::assertStringContainsString('SSI Include Content', $responseBody);
        self::assertStringContainsString('This content should be cached as SSI include', $responseBody);
        self::assertStringNotContainsString('<!--# include', $responseBody); // No SSI comment should be present

        // Verify that NO cache file was created
        self::assertFileDoesNotExist($absoluteFilename);

        // Also verify cache entry was not stored in database
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        self::assertFalse($cache->has($expectedFilename));
    }

    /**
     * @throws NoSuchCacheException
     */
    #[Test]
    public function cacheFileCreatedWhenNoBackendUserLoggedIn(): void
    {
        // Import a page tree with a test page
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        // Set extension configuration
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ssi_include']['disabled'] = '0';
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ssi_include']['method'] = 'ssi';

        // Set up TypoScript template for the test page
        $this->setUpFrontendRootPage(1, [
            'EXT:ssi_include/Tests/Fixtures/TypoScript/test_page.typoscript'
        ]);

        // Get expected SSI include filename
        $expectedFilename = 'default_0_testinclude.html';

        // Verify file doesn't exist before request
        $absoluteFilename = GeneralUtility::makeInstance(FilenameUtility::class)->getAbsoluteFilename($expectedFilename);
        if (file_exists($absoluteFilename)) {
            unlink($absoluteFilename);
        }

        self::assertFileDoesNotExist($absoluteFilename);

        // Create a ServerRequest with the URI
        $request = (new InternalRequest());
        $request = $request->withMethod('GET');

        $context = new InternalRequestContext();
        $response = $this->executeFrontendSubRequest($request, $context);

        // Verify the response was successful
        self::assertEquals(200, $response->getStatusCode());

        $responseBody = (string)$response->getBody();

        // When NO backend user is logged in, SSI include comment should be present
        self::assertStringContainsString('<!--# include', $responseBody);
        self::assertStringContainsString('testinclude', $responseBody);

        // Verify that cache file WAS created
        self::assertFileExists($absoluteFilename);

        // Verify cache file contains the expected content
        $cacheFileContent = file_get_contents($absoluteFilename);
        self::assertIsString($cacheFileContent);
        self::assertStringContainsString('SSI Include Content', $cacheFileContent);
        self::assertStringContainsString('This content should be cached as SSI include', $cacheFileContent);

        // Verify cache entry was stored in database
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        self::assertTrue($cache->has($expectedFilename));

        // Clean up
        @unlink($absoluteFilename);
    }

    /**
     * @throws NoSuchCacheException
     */
    #[Test]
    public function dataHandlerClearCacheRemovesCacheEntry(): void
    {
        $entryIdentifier = 'datahandler_test_entry.html';
        $data = '<h1>DataHandler Test Content</h1>';

        // Store cache entry first
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cache = $cacheManager->getCache('aus_ssi_include_cache');
        assert($cache->getBackend() instanceof SsiIncludeCacheBackend);
        $cache->set($entryIdentifier, $data);

        // Verify cache entry exists
        self::assertTrue($cache->has($entryIdentifier));
        $absoluteFilename = GeneralUtility::makeInstance(FilenameUtility::class)->getAbsoluteFilename($entryIdentifier);
        self::assertFileExists($absoluteFilename);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        // Set up backend user context for DataHandler
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['BE_USER'] = $backendUser;

        // Use DataHandler to clear cache
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], []);
        $dataHandler->clear_cacheCmd('all');

        // Verify cache entry was removed
        self::assertFalse($cache->has($entryIdentifier));
        self::assertFileDoesNotExist($absoluteFilename);
    }
}
