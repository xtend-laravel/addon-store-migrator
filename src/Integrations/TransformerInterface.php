<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations;

use Illuminate\Support\Collection;

interface TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection;
}
