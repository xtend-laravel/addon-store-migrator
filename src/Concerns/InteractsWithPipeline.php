<?php

namespace XtendLunar\Addons\StoreMigrator\Concerns;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;

trait InteractsWithPipeline
{
    protected function prepareThroughPipeline(mixed $passable, array $pipes, string $method = 'handle'): Collection
    {
        return app(Pipeline::class)
            ->send($passable)
            ->through($pipes)
            ->via($method)
            ->thenReturn();
    }
}
