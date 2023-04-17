<?php

namespace XtendLunar\Addons\StoreMigrator;

use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lunar\Hub\Facades\Menu;
use StoreMigrator\Database\Seeders\StoreMigratorIntegratorSeeder;
use XtendLunar\Addons\StoreMigrator\Commands\PrestashopMigrationSync;
use XtendLunar\Addons\StoreMigrator\Livewire\Components\StoreMigratorIntegrationsTable;

class StoreMigratorProvider extends XtendAddonProvider
{
    public function register()
    {
	    $this->loadRoutesFrom(__DIR__.'/../routes/hub.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xtend-lunar::store-migrator');
	    $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminhub');
        $this->mergeConfigFrom(__DIR__.'/../config/store-migrator.php', 'store-migrator');
	    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

		$this->registerSeeders([
			StoreMigratorIntegratorSeeder::class,
	    ]);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrestashopMigrationSync::class,
            ]);
        }

	    Livewire::component('hub.components.store-migrator-integrations.table', StoreMigratorIntegrationsTable::class);

	    $this->registerWithSidebarMenu();
    }

	protected function registerSeeders(array $seeders = []): void
	{
		$this->callAfterResolving(DatabaseSeeder::class, function (DatabaseSeeder $seeder) use ($seeders) {
			collect($seeders)->each(
				fn ($seederClass) => $seeder->call($seederClass),
			);
		});
	}

	protected function registerWithSidebarMenu(): void
	{
		Event::listen(LocaleUpdated::class, function () {
			Menu::slot('sidebar')
			    ->group('hub.configure')
			    ->section('hub.store-migrator')
			    ->name('Store Migrator')
			    ->route('hub.store-migrator')
			    ->icon('database');
		});
	}
}
