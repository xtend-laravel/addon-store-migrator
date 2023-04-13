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
use Lunar\FieldTypes\ListField;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\CollectionGroup;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CategoriesRequest;

class CategorySync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $category;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $categoryId
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
        $request = new CategoriesRequest(categoryId: $this->categoryId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $this->category = $this->prepareThroughPipeline(
            passable: PrestashopConnector::mapFields(
                collect($response->json('categories')[0]), 'categories', 'lunar'
            ),
            pipes: [
                TranslationTransformer::class,
                FieldMapKeyTransformer::class,
                FieldLegacyTransformer::class,
            ],
            method: 'transform',
        );
    }

    protected function sync()
    {
        $this->createCollection();
    }

    protected function createCollection(): ?CollectionModel
    {
        $groupId = CollectionGroup::firstOrCreate(['handle' => 'categories'], ['name' => 'Categories'])->id;

        if (! $collection = $this->collectionExists($groupId)) {
            $collection = CollectionModel::create([
                'type' => 'category',
                'collection_group_id' => $groupId,
                'attribute_data' => $this->getAttributeData(),
                'legacy_data' => $this->category->get('legacy'),
            ], CollectionModel::find(1));
        }

        return $collection;
    }

    protected function collectionExists(int $groupId): ?CollectionModel
    {
        return CollectionModel::where('legacy_data->id', $this->category->get('legacy')->get('id'))
            ->where('collection_group_id', $groupId)
            ->where('type', 'category')
            ->first();
    }

    protected function getAttributeData(): array
    {
        /** @var Collection $attributes */
        $attributes = Attribute::whereAttributeType(CollectionModel::class)->get();

        $categoryAttributes = $this->category->filter(fn ($value, $field) => str_starts_with($field, 'attribute'))->mapWithKeys(
            fn ($value, $field) => [Str::afterLast($field, '.') => $value]
        );

        $attributeData = [];
        foreach ($categoryAttributes as $attributeHandle => $value) {
            $attribute = $attributes->first(fn ($att) => $att->handle == $attributeHandle);
            if (! $attribute) {
                continue;
            }

            if ($attribute->type == TranslatedText::class) {
                $attributeData[$attributeHandle] = new TranslatedText($value);

                continue;
            }

            if ($attribute->type == ListField::class) {
                $attributeData[$attributeHandle] = new ListField((array) $value);
            }
        }

        return $attributeData;
    }
}
