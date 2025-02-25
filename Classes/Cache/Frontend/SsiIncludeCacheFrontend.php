<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Cache\Frontend;

use InvalidArgumentException;
use TYPO3\CMS\Core\Cache\Exception;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;

/**
 * A cache frontend for SSI include cache entries.
 * If you use with another backend, make sure the backend supports cache entries with the pattern defined here
 */
class SsiIncludeCacheFrontend extends AbstractFrontend
{
    public const PATTERN_ENTRYIDENTIFIER = '/^[a-zA-Z0-9_-]+\.html$/';

    /**
     * @inheritdoc
     * @throws Exception
     * @throws InvalidDataException
     * @param list<string> $tags
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null): void
    {
        /** @noinspection DuplicatedCode */
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new InvalidArgumentException(
                '"' . $entryIdentifier . '" is not a valid cache entry identifier.',
                1233058264
            );
        }

        foreach ($tags as $tag) {
            if (!$this->isValidTag($tag)) {
                throw new InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1233058269);
            }
        }

        $this->backend->set($entryIdentifier, $data, $tags, $lifetime);
    }

    /**
     * @inheritdoc
     */
    public function get($entryIdentifier)
    {
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new InvalidArgumentException(
                '"' . $entryIdentifier . '" is not a valid cache entry identifier.',
                1233058294
            );
        }

        return $this->backend->get($entryIdentifier);
    }

    /**
     * @inheritdoc
     */
    public function isValidEntryIdentifier($identifier): bool
    {
        return preg_match(static::PATTERN_ENTRYIDENTIFIER, $identifier) === 1;
    }
}
