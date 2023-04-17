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
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OptionRequest;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\OptionValueRequest;

class OptionSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $option;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $optionId
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
        $request = new OptionRequest(optionId: $this->optionId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $optionValues = $response->json('product_options')[0]['associations']['product_option_values'] ?? [];

        $this->option = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('product_options')[0]), 'product_options', 'lunar'
            ),
            pipes: [
                TranslationTransformer::class,
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );

        if ($optionValues) {
            $this->option->put('values', $optionValues);
        }
    }

    protected function sync()
    {
        $this->createOption();
    }

    protected function createOption(): ?ProductOption
    {
        $name = $this->option->get('name')->get(app()->getLocale());

        if (! $option = ProductOption::where('name->en', $name)->first()) {
            $option = ProductOption::create([
                'handle' => Str::slug($name),
                'name' => $this->option->get('name'),
                'legacy_data' => $this->option->get('legacy'),
            ]);
        }

        $this->prepareOptionValues();

        if ($this->option->has('values')) {
            $option->values()->createMany(
                $this->createOptionValues()
            );
        }

        return $option;
    }

    protected function prepareOptionValues()
    {
        $optionValueIds = collect($this->option->get('values'))->pluck('id');
        if (! $optionValueIds) {
            return;
        }

        $optionValueIds = $optionValueIds->filter(
            fn ($id) => ! ProductOptionValue::where('legacy_data->id', $id)->exists()
        );

        $request = new OptionValueRequest;
        $request->query()->merge([
            'filter[id]' => "[{$optionValueIds->implode('|')}]",
            'display' => 'full',
        ]);
	    $response = PrestashopConnector::make()->send($request);

        $optionValues = collect($response->json('product_option_values'))->map(function ($optionValue) {
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

        $this->option->put('values', $optionValues);
    }

    protected function createOptionValues(): array
    {
        return $this->option->get('values')->map(function ($optionValue) {
            return [
                'name' => $optionValue->get('name'),
                'position' => $optionValue->get('position'),
                'legacy_data' => $optionValue->get('legacy'),
            ];
        })->toArray();
    }
}
