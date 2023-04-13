<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Prestashop;

use Illuminate\Support\Facades\File;
use XtendLunar\Addons\StoreMigrator\Integrations\AbstractFieldMapper;

class FieldMapper extends AbstractFieldMapper
{
    protected array $entities = [
        'products',
        'combinations',
        'product_options',
        'product_option_values',
        'product_features',
        'product_feature_values',
        'categories',
        'attributes',
        'categories',
        'brands',
        'variants',
        'orders',
        'order_details',
        'customers',
        'addresses',
        'groups',
        'carts',
    ];

    protected array $localeMap = [
        0 => 'fr',
        1 => 'en',
    ];

    protected string $translationKey = 'value';

    public function translatableAttributes(): array
    {
        return [
            'products' => [
                'name',
                'description',
                'description_short',
                'meta_title',
                'meta_description',
            ],
            'combinations' => null,
            'categories' => [
                'name',
                'description',
                'meta_title',
                'meta_description',
            ],
            'product_options' => [
                'name',
                'public_name',
            ],
            'product_option_values' => [
                'name',
            ],
            'product_features' => [
                'name',
            ],
            'product_feature_values' => [
                'value',
            ],
            'customers' => [
                'name',
            ],
            'groups' => [
                'name',
            ],
            'addresses' => null,
            'carts' => null,
            'orders' => null,
            'order_details' => null,
        ];
    }

    public static function getLegacyFieldsLookup(string $destination, string $entity): array
    {
        $filePath = __DIR__."/data/prestashop-$destination-$entity.json";

        return File::exists($filePath) ? collect(json_decode(File::get($filePath), true))->get('legacy_lookup') : [];
    }
}
