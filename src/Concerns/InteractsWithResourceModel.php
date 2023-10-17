<?php

namespace XtendLunar\Addons\StoreMigrator\Concerns;

use Illuminate\Database\Eloquent\Model;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResource;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorResourceModel;

trait InteractsWithResourceModel
{
    protected StoreMigratorResourceModel|Model $resourceModel;

    protected function setResourceSourceId(int $sourceId, string $resourceName): self
    {
        /** @var StoreMigratorResource $resource */
        $resource = StoreMigratorResource::where('name', $resourceName)->sole();
        $this->resourceModel = $resource->models()->firstOrCreate([
            'source_id' => $sourceId,
        ]);

        return $this;
    }
}
