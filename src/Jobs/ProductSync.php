<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Xtend\Extensions\Lunar\Core\Models\Product;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithResourceModel;
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
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResource;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

class ProductSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithDebug;
    use InteractsWithPipeline;
    use InteractsWithResourceModel;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    ) {
        $this->setResourceSourceId($this->productId);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->benchmark([
            'prepare' => fn() => $this->prepare(),
            'sync' => fn() => $this->product->isNotEmpty()
                ? DB::transaction(fn() => $this->sync())
                : null,
        ])->log();
    }

    protected function prepare(): void
    {
        $this->benchmark([
            'prepare.product' => fn() => $this->prepareProduct(),
            'prepare.variants' => fn() => $this->prepareVariants(),
        ])->log();
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

    protected function prepareVariants(): void
    {
        if (!Arr::has($this->product, 'legacy.id_default_combination')) {
            return;
        }

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
        $this->prepareThroughPipeline(
            passable: [
                'product' => $this->product,
                'resourceModel' => $this->resourceModel,
            ],
            pipes: [
                ProductSave::class,
                ProductImageSave::class,
                ProductVariantSave::class,
                BrandAssociation::class,
                CollectionAttach::class,
            ],
        );

        $this->resourceModel->status = 'created';
        $this->resourceModel->save();
    }
}
