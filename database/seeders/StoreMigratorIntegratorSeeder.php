<?php

namespace StoreMigrator\Database\Seeders;

use Illuminate\Database\Seeder;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorIntegration;

class StoreMigratorIntegratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /** @var StoreMigratorIntegration $integration */
        $integration = StoreMigratorIntegration::updateOrCreate([
            'name' => 'Prestashop Store Inventory',
            'integration' => 'prestashop',
        ], [
            'resources' => ['categories', 'products'],
        ]);

        $integration->resources()->updateOrCreate([
            'name' => 'categories',
        ], [
            'field_map' => $this->getCategoryFieldMap(),
        ]);

        $integration->resources()->updateOrCreate([
            'name' => 'products',
        ], [
            'field_map' => $this->getProductFieldMap(),
            'settings' => [
                'image_conversions' => true,
                'sync_only_has_images' => true,
            ],
        ]);
    }

    protected function getCategoryFieldMap(): array
    {
        return [
            'id' => 'legacy->id',
            'id_parent' => 'legacy->parent_id',
            'name' => 'attribute.details.name',
            'position' => 'legacy->position',
            'active' => 'legacy->active',
            'description_short' => 'attribute.details.description_short',
            'description' => 'attribute.details.description',
            'meta_title' => 'attribute.seo.meta_title',
            'meta_description' => 'attribute.seo.meta_description',
            'date_add' => 'created_at',
            'date_upd' => 'updated_at',
        ];
    }

    protected function getProductFieldMap(): array
    {
        return [
            'id' => 'legacy->id_product',
            'id_manufacturer' => 'legacy->id_manufacturer',
            'id_category_default' => 'legacy->id_category_default',
            'id_default_combination' => 'legacy->id_default_combination',
            'name' => 'attribute.details.name',
            'description_short' => 'attribute.details.description_short',
            'description' => 'attribute.details.description',
            'meta_title' => 'attribute.seo.meta_title',
            'meta_description' => 'attribute.seo.meta_description',
            'manufacturer_name' => 'legacy->manufacturer_name',
            'price' => 'legacy->price',
            'specific_price' => 'legacy->specific_price',
            'reduction_amount' => 'legacy->reduction_amount',
            'active' => 'legacy->active',
            'reference' => 'legacy->sku',
            'date_add' => 'created_at',
            'date_upd' => 'updated_at',
            'quantity' => 'stock',
            'legacy_lookup' => [
                'id_manufacturer' => 'brands:name',
                'id_category_default' => 'collections:name',
            ],
        ];
    }
}
