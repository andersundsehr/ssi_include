<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Register;

use TYPO3\CMS\Core\SingletonInterface;

class LastRenderedContentRegister implements SingletonInterface
{
    /**
     * @var array<string, string>
     */
    private array $lastRenderedContent = [];

    public function set(string $key, string $content): void
    {
        $this->lastRenderedContent[$key] = $content;
    }

    public function get(string $key): string
    {
        if (!isset($this->lastRenderedContent[$key])) {
            return '';
        }
        return $this->lastRenderedContent[$key];
    }
}
