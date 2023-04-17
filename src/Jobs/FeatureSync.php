<?php

namespace XtendLunar\Addons\StoreMigrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\FeatureRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\FeatureValueRequest;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;
use XtendLunar\Features\ProductFeatures\Models\ProductFeatureValue;

class FeatureSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $feature;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $featureId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->prepare();
        DB::transaction(fn () => $this->sync());
    }

    protected function prepare()
    {
        $request = new FeatureRequest(featureId: $this->featureId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $this->feature = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('product_features')[0]), 'product_features', 'lunar'
            ),
            pipes: [
                TranslationTransformer::class,
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        $this->prepareFeatureValues();
    }

    protected function prepareFeatureValues(): array
    {
        $request = new FeatureValueRequest;
        $request->query()->merge([
            'filter[id_feature]' => $this->featureId,
            'display' => 'full',
        ]);
		$response = PrestashopConnector::make()->send($request);

        $featureValues = collect($response->json('product_feature_values'))->map(function ($combination) {
            return $this->prepareThroughPipeline(
                passable: PrestashopConnector::mapFields(collect($combination), 'product_feature_values', 'lunar'),
                pipes: [
                    TranslationTransformer::class,
                    FieldMapKeyTransformer::class,
                    FieldLegacyTransformer::class,
                ],
                method: 'transform',
            );
        });

        $this->feature->put('values', $featureValues);

        return $featureValues->toArray();
    }

    protected function sync()
    {
        $this->saveFeature();
    }

    protected function saveFeature(): ?ProductFeature
    {
        $name = $this->feature->get('name')->get(app()->getLocale());
        $feature = ProductFeature::updateOrCreate([
            'legacy_data->id' => $this->feature->get('legacy')->get('id'),
        ], [
            'handle' => Str::slug($name),
            'name' => $this->feature->get('name'),
            'legacy_data' => $this->feature->get('legacy'),
        ]);

        if ($this->feature->has('values')) {
            $feature->values()->createMany(
                $this->createFeatureValues($feature)
            );
        }

        return $feature;
    }

    protected function createFeatureValues(ProductFeature $feature): array
    {
        $featureValues = collect($this->feature->get('values'))->filter(
            fn ($featureValue) => ! ProductFeatureValue::where('legacy_data->id', $featureValue->get('legacy')->get('id'))->exists()
        );

        return $featureValues->map(function ($featureValue) use ($feature) {
            $position = ProductFeatureValue::where('product_feature_id', $feature->id)->count() + 1;

            return [
                'position' => $position,
                'name' => $featureValue->get('name'),
                'legacy_data' => $featureValue->get('legacy'),
            ];
        })->toArray();
    }
}
