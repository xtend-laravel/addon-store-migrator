<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue\BrandAssociation;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue\CollectionAttach;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue\ProductImageSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue\ProductSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Processors\Catalogue\ProductVariantSave;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CombinationsRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ImageRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OptionValueRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\ProductsRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\SpecificPricesRequest;

class ProductSync implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $product;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $productId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->prepare();

        //$exists = Product::where('legacy_data->id_product', $this->productId)->first();
	    if ($this->product->count()) {
			dd($this->product->toArray());
		    //DB::transaction(fn() => $this->sync());
	    }
    }

    protected function prepare(): void
    {
        $this->prepareProduct();

        if (Arr::get($this->product, 'legacy.id_default_combination') > 0) {
            //$this->prepareCombinations();
        }

        // @todo Prepare images for product and combinations
    }

    protected function prepareProduct(): void
    {
        $request = new ProductsRequest(productId: $this->productId);
		$request->query()->merge([
			'price[specific_price][use_reduction]' => true,
			'price[reduction_amount][only_reduction]' => true,
			'display' => 'full',
		]);

        $response = PrestashopConnector::make()->send($request);
        $categories = $response->json('products')[0]['associations']['categories'] ?? [];
        $images = $response->json('products')[0]['associations']['images'] ?? [];

		if (!$images) {
			// No images so skip this product for now
			$this->product = collect();
			return;
		}

        $this->product = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('products')[0]), 'products', 'lunar'
            ),
            pipes: [
                TranslationTransformer::class,
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        if ($categories) {
            $this->product->put('categories', $categories);
        }

        if ($images) {
            $this->product->put('images', $this->prepareProductImages());
        }

	    $this->product->put('prices', $this->prepareProductPrices());
    }

    protected function prepareProductImages(): array
    {
        $request = new ImageRequest('products', $this->productId);
		$response = PrestashopConnector::make()->send($request);

        $xml = new \SimpleXMLElement($response->body());

        $imageUrls = collect();
        foreach ($xml->image->declination as $image) {
            $attributes = $image->attributes('xlink', true);
            $wsKey = '?ws_key='.config('services.prestashop_webservice.key');
            $imageUrls->push($attributes['href'].$wsKey);
        }

        //$ids = $imageUrls->values()->map(fn ($url) => basename($url))->toArray();
        return $imageUrls->values()->toArray();
    }

	protected function prepareProductPrices(): array
	{
		$request = new SpecificPricesRequest;
		$request->query()->merge([
			'filter[id_product]' => "[{$this->productId}]",
			'display' => 'full',
		]);

		$response = PrestashopConnector::make()->send($request);
		return $response->json('specific_prices') ?? [];
	}

    protected function prepareCombinations(): void
    {
        $request = new CombinationsRequest;
        $request->query()->merge([
            'filter[id_product]' => $this->productId,
            'display' => 'full',
        ]);
		$response = PrestashopConnector::make()->send($request);

        // $combinations = collect($response->json('combinations'))->filter(fn ($combination) => count($combination['associations']['images'] ?? []));
        //
        // if ($combinations->isNotEmpty()) {
        //     dd('Images found for combination!', $combinations);
        // }

        $combinations = collect($response->json('combinations'))->map(function ($combination) {

            $combination['associations']['product_option_values'] = $this->prepareOptionValues($combination)->toArray();

            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($combination), 'combinations', 'lunar'),
                pipes: [
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });

        $this->product->put('combinations', $combinations);
    }

    protected function prepareOptionValues(array $combination): Collection
    {
        $optionValueIds = collect($combination['associations']['product_option_values'])->pluck('id');

        $request = new OptionValueRequest;
        $request->query()->merge([
            'filter[id]' => "[{$optionValueIds->implode('|')}]",
            'display' => 'full',
        ]);
	    $response = PrestashopConnector::make()->send($request);

        return collect($response->json('product_option_values'))->map(function ($optionValue) {
            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($optionValue), 'product_option_values', 'lunar'),
                pipes: [
                    TranslationTransformer::class,
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });
    }

    protected function sync(): void
    {
        //$this->product->put('legacy_lookup', FieldMapper::getLegacyFieldsLookup('lunar', 'products'));

        $this->prepareThroughPipeline(
            passable: $this->product,
            pipes: [
                ProductSave::class,
                ProductImageSave::class,
                ProductVariantSave::class,
                BrandAssociation::class,
                CollectionAttach::class,
            ],
        );
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->productId;
    }
}
