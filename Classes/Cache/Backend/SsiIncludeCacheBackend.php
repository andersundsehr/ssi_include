<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Cache\Backend;

use AUS\SsiInclude\Utility\FilenameUtility;
use InvalidArgumentException;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webimpress\SafeWriter\Exception\ExceptionInterface;
use Webimpress\SafeWriter\FileWriter;

class SsiIncludeCacheBackend extends Typo3DatabaseBackend
{
    private readonly FilenameUtility $filenameUtility;

    /**
     * path where the include files are saved for use with the webserver
     */
    private string $ssiIncludeDir = '/typo3temp/tx_ssiinclude/';

    private bool $storeData = true;

    /**
     * @inheritdoc
     * @param array<string, mixed> $options
     */
    public function __construct($context, array $options = [])
    {
        parent::__construct($context, $options);

        $this->filenameUtility = GeneralUtility::makeInstance(FilenameUtility::class);
    }

    public function getSsiIncludeDir(): string
    {
        return $this->ssiIncludeDir;
    }

    /**
     * @return list<string>
     */
    private function getSsiIncludeDirFiles(): array
    {
        $publicIncludeDir = Environment::getPublicPath() . $this->ssiIncludeDir;
        return glob($publicIncludeDir . '*.html') ?: [];
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

        parent::set($entryIdentifier, $this->storeData ? $data : '', $tags, $lifetime);

        $absolutePath = $this->filenameUtility->getAbsoluteFilename($entryIdentifier);

        GeneralUtility::mkdir_deep(dirname($absolutePath));
        GeneralUtility::fixPermissions(dirname($absolutePath));

        FileWriter::writeFile($absolutePath, $data);
        GeneralUtility::fixPermissions($absolutePath);
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function has($entryIdentifier): bool
    {
        $data = parent::has($entryIdentifier);
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

        return parent::remove($entryIdentifier);
    }

    /**
     * @inheritdoc
     */
    public function flush(): void
    {
        foreach ($this->getSsiIncludeDirFiles() as $file) {
            unlink($file);
        }

        parent::flush();
    }

    /**
     * @inheritdoc
     * @throws NoSuchCacheException
     */
    public function collectGarbage(): void
    {
        // remove outdated things
        parent::collectGarbage();

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
        parent::flushByTags($tags);
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
        return parent::findIdentifiersByTag($tag);
    }
}
