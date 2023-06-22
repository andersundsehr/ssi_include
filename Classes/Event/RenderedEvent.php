<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Event;

class RenderedEvent
{
    protected string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
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
