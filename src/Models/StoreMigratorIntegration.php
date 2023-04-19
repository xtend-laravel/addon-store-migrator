<?php

namespace XtendLunar\Addons\StoreMigrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class StoreMigratorIntegration
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreMigratorIntegration extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $casts = [
		'resources' => 'array',
	];

	protected $table = 'xtend_store_migrator_integrations';

	public function resources(): HasMany
	{
		return $this->hasMany(StoreMigratorResource::class, 'integration_id');
	}
}
