<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Event;

class ShouldRenderFileEvent
{
    public function __construct(private bool $shouldRender)
    {

    }

    public function setShouldRender(bool $shouldRender): void
    {
        $this->shouldRender = $shouldRender;
    }

    public function shouldRender(): bool
    {
        return $this->shouldRender;
    }
}
