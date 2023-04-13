<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar;

use XtendLunar\Addons\StoreMigrator\Integrations\AbstractFieldMapper;

class FieldMapper extends AbstractFieldMapper
{
    protected array $entities = [
        'products',
        'collections',
        'attributes',
        'categories',
        'brands',
        'variants',
        'orders',
        'customers',
        'carts',
    ];

    public function translatableAttributes(): array
    {
        return [
            'products' => [
                'attribute.name',
                'attribute.description',
            ],
            'collections' => [
                'name',
                'description',
            ],
        ];
    }
}
