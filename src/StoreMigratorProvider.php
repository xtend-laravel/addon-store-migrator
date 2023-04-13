<?php

namespace XtendLunar\Addons\StoreMigrator;

use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use XtendLunar\Addons\StoreMigrator\Commands\OrderMigrationSync;
use XtendLunar\Addons\StoreMigrator\Commands\PrestashopMigrationSync;

class StoreMigratorProvider extends XtendAddonProvider
{
    public function register()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xtend-lunar::store-migrator');
        $this->mergeConfigFrom(__DIR__.'/../config/store-migrator.php', 'store-migrator');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrestashopMigrationSync::class,
                OrderMigrationSync::class,
            ]);
        }
    }
}
