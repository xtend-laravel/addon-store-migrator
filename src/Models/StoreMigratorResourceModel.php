<?php

namespace XtendLunar\Addons\StoreMigrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class StoreMigratorResourceModel
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreMigratorResourceModel extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $table = 'xtend_store_migrator_resource_models';

	protected $casts = [
		'failed_at' => 'datetime',
        'debug' => 'array',
	];

	public function resource(): BelongsTo
	{
		return $this->belongsTo(StoreMigratorResource::class);
	}
}
