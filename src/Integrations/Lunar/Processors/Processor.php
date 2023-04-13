<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors;

use Illuminate\Support\Collection;

abstract class Processor
{
    public function handle(Collection $data, \Closure $next): mixed
    {
        return $next($this->process($data));
    }

    abstract public function process(Collection $data): mixed;
}
