<?php

namespace XtendLunar\Addons\StoreMigrator;

use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lunar\Hub\Facades\Menu;
use XtendLunar\Addons\StoreMigrator\Commands\PrestashopMigrationSync;
use XtendLunar\Addons\StoreMigrator\Livewire\Components\StoreMigratorTable;

class StoreMigratorProvider extends XtendAddonProvider
{
    public function register()
    {
	    $this->loadRoutesFrom(__DIR__.'/../routes/hub.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xtend-lunar::store-migrator');
	    $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminhub');
        $this->mergeConfigFrom(__DIR__.'/../config/store-migrator.php', 'store-migrator');
	    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrestashopMigrationSync::class,
            ]);
        }

	    Livewire::component('hub.components.store-migrator.table', StoreMigratorTable::class);

	    $this->registerWithSidebarMenu();
    }

	protected function registerWithSidebarMenu(): void
	{
		Event::listen(LocaleUpdated::class, function () {
			Menu::slot('sidebar')
			    ->group('hub.configure')
			    ->section('hub.store-migrator')
			    ->name('Store Migrator')
			    ->route('hub.store-migrator.index')
			    ->icon('database');
		});
	}
}
