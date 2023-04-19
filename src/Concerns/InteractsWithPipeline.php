<?php

namespace XtendLunar\Addons\StoreMigrator\Concerns;

use Illuminate\Pipeline\Pipeline;

trait InteractsWithPipeline
{
    protected function prepareThroughPipeline(mixed $passable, array $pipes, string $method = 'handle'): mixed
    {
        return app(Pipeline::class)
            ->send($passable)
            ->through($pipes)
            ->via($method)
            ->thenReturn();
    }
}
