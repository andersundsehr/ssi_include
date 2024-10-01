<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Event;

class RenderedEvent
{
    public function __construct(protected string $html)
    {
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }
}
