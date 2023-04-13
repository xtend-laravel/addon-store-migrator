<?php

namespace XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Processor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductImageSave extends Processor
{
    public function process(Collection $product): mixed
    {
        /** @var \Lunar\Models\Product $productModel */
        $productModel = $product->get('productModel');
        $images = collect($product->get('images'));
        if ($images->isEmpty()) {
            return $product;
        }

        $images->each(
            fn ($image, $key) => $this->saveImage($image, $productModel, $key)
        );

        return $product;
    }

    protected function saveImage(string $image, Product $productModel, int $key): void
    {
        // @todo Check if image exists note remotely is too slow
        // || !Http::get($image)->ok()

        // if ($this->imageExists($image, $productModel)) {
        //     dd($image);
        //     return;
        // }

        $media = $productModel
            ->addMediaFromUrl($image)
            ->toMediaCollection('products');

        $media->setCustomProperty('primary', $key === 0);
        $media->save();
    }

    protected function imageExists(string $image, Product $productModel): bool
    {
        // @todo Allow to replace image on force update?

        $filename = basename(parse_url($image, PHP_URL_PATH));

        return $productModel->getMedia('products')->map(
            fn (Media $media) => Str::of($media->file_name)->beforeLast('.')
        )->contains($filename);

    }
}
