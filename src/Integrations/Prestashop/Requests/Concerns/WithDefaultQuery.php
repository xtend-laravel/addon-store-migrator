<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\Concerns;

trait WithDefaultQuery
{
    public function defaultQuery(): array
    {
        return [
            //'limit' => 100,
            //'display' => 'full',
            'output_format' => 'JSON',
        ];
    }
}
