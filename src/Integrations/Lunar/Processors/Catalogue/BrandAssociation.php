<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue;

use Illuminate\Support\Collection;
use Lunar\Models\Brand;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;

class BrandAssociation extends Processor
{
    public function process(Collection $product): mixed
    {
        /** @var \Lunar\Models\Product $productModel */
        $productModel = $product->get('productModel');
        $productModel->brand()->dissociate()->save();
        if (filled($brandName = $product->get('legacy')->get('manufacturer_name'))) {
            $brand = Brand::query()->updateOrCreate(['name' => $brandName]);
            $productModel->brand()->associate($brand)->save();
        }

        $product->put('productModel', $productModel);

        return $product;
    }
}
