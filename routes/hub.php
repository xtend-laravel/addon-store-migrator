<?php

use Illuminate\Support\Facades\Route;
use Lunar\Hub\Http\Middleware\Authenticate;
use XtendLunar\Addons\StoreMigrator\Livewire\Pages\StoreMigratorIndex;

/**
 * Payment Gateways routes.
 */
Route::group([
    'prefix' => config('lunar-hub.system.path', 'hub'),
    'middleware' => ['web', Authenticate::class, 'can:settings:core'],
], function () {
    Route::get('/store-migrator', StoreMigratorIndex::class)->name('hub.store-migrator.index');
});
