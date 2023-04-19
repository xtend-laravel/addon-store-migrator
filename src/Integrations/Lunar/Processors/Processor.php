<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors;

use Illuminate\Support\Str;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithResourceModel;

abstract class Processor
{
    use InteractsWithDebug;
    use InteractsWithResourceModel;

    protected mixed $process;

    public function handle(mixed $passable, \Closure $next): mixed
    {
        $this->resourceModel = $passable['resourceModel'];

        $processKey = Str::snake(class_basename($this), '-');
        $this->benchmark([
            $processKey => fn () => $this->process(...$passable),
        ])->log();

        return $next($passable);
    }
}
