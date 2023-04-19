<?php

namespace XtendLunar\Addons\StoreMigrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class StoreMigratorResource
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreMigratorResource extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $casts = [
		'field_map' => 'array',
		'settings' => 'array',
	];

	protected $table = 'xtend_store_migrator_resources';

	public function integration(): BelongsTo
	{
		return $this->belongsTo(StoreMigratorIntegration::class, 'integration_id');
	}

    public function models(): HasMany
    {
        return $this->hasMany(StoreMigratorResourceModel::class, 'resource_id');
    }
}
