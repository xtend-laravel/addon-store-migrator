<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue;

use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class CollectionAttach extends Processor
{
    public function process(Collection $product): mixed
    {
        /** @var \Lunar\Models\Product $productModel */
        $productModel = $product->get('productModel');
        $categoryIds = collect($product->get('categories'))->pluck('id');

        $collections = $categoryIds->map(
            fn ($categoryId) => \Xtend\Extensions\Lunar\Core\Models\Collection::where('legacy_data->id', $categoryId)->first()
        )->filter();

        if ($collections->isNotEmpty()) {

            $productModel->collections()->sync($collections->pluck('id'));

            $productModel->primary_category_id = $collections->pluck('id')->last() ?? null;
            $productModel->save();

            $productModel->refresh();
            $product->put('productModel', $productModel);
        }

        return $product;
    }
}
