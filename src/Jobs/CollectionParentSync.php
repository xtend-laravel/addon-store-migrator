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
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\CollectionGroup;
use XtendLunar\Addons\StoreMigrator\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldLegacyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\FieldMapKeyTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Lunar\Transformers\TranslationTransformer;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\PrestashopConnector;
use XtendLunar\Addons\StoreMigrator\Integrations\Prestashop\Requests\CategoriesRequest;

class CollectionParentSync implements ShouldQueue
{
    use Dispatchable, InteractsWithPipeline, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::transaction(fn () => $this->sync());
    }

    protected function sync()
    {
        // @note this will reset all parents back to 1 and truncates collection product table
        $this->resetCategoryParents();

        CollectionModel::all()->each(
            function (CollectionModel $collection): void {
                if ($collection->getParentId() === 1) {
                    $parentLookup = $this->lookupParent($collection);
                    if ($parentLookup && $parentLookup->id !== 1) {
                        $collection->appendToNode($parentLookup);
                        $collection->save();
                    }
                }
            }
        );

        CollectionModel::fixTree();
    }

    protected function lookupParent(CollectionModel $collection): ?CollectionModel
    {
        $parentId = $collection->legacy_data->get('parent_id');

        $request = new CategoriesRequest(categoryId: $parentId);
        $request->query()->add('display', 'full');
        $response = PrestashopConnector::make()->send($request);

        $category = $this->prepareThroughPipeline(
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

        $groupId = CollectionGroup::firstOrCreate(['handle' => 'categories'], ['name' => 'Categories'])->id;

        return $this->collectionExists($category, $groupId);
    }

    protected function collectionExists(Collection $category, int $groupId): ?CollectionModel
    {
        return CollectionModel::where('legacy_data->id', $category->get('legacy')->get('id'))
            ->where('collection_group_id', $groupId)
            ->where('type', 'category')
            ->first();
    }

    protected function findCollectionByName(Collection $category, int $groupId): ?CollectionModel
    {
        $categoryCollection = CollectionModel::where('collection_group_id', $groupId)->get();

        return $categoryCollection->filter(function (CollectionModel $collection) use ($category) {
            $collectionAttr = Str::of($collection->translateAttribute('name'))->lower();
            $categoryName = Str::of($category->get('attribute.details.name')->get(app()->getLocale()))->lower();

            return $collectionAttr->is($categoryName);
        })->first();
    }

    private function resetCategoryParents(): void
    {
        CollectionModel::whereNull('parent_id')->get()->skip(1)->each(function (CollectionModel $collection) {
            $collection->appendToNode(CollectionModel::find(1))->save();
        });

        CollectionModel::fixTree();
        CollectionModel::all()->each(
            fn (CollectionModel $collection) => $collection->products()->delete()
        );
    }
}
