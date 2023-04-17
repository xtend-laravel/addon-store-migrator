<?php

namespace XtendLunar\Addons\StoreMigrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
		return $this->belongsTo(StoreMigratorIntegration::class);
	}
}
