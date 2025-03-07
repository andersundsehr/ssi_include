<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Register;

use TYPO3\CMS\Core\SingletonInterface;

class LastRenderedContentRegister implements SingletonInterface
{
    private string $lastRenderedContent = '';

    public function set(string $content): void
    {
        $this->lastRenderedContent = $content;
    }

    public function get(): string
    {
        return $this->lastRenderedContent;
    }
}
