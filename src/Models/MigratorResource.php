<?php

namespace XtendLunar\Addons\StoreMigrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigratorResource extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $table = 'xtend_store_migrator_resources';
}
