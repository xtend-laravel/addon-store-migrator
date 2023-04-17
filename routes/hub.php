<?php

use Illuminate\Support\Facades\Route;
use Lunar\Hub\Http\Middleware\Authenticate;
use XtendLunar\Addons\StoreMigrator\Livewire\Pages\StoreMigrator;

/**
 * Payment Gateways routes.
 */
Route::group([
    'prefix' => config('lunar-hub.system.path', 'hub'),
    'middleware' => ['web', Authenticate::class, 'can:settings:core'],
], function () {
    Route::get('/store-migrator', StoreMigrator::class)->name('hub.store-migrator');
    Route::get('/store-migrator/{migrator}', StoreMigrator::class)->name('hub.store-migrator.show');
});
