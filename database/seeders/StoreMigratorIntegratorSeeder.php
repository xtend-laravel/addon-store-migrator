<?php

namespace StoreMigrator\Database\Seeders;

use Illuminate\Database\Seeder;
use XtendLunar\Addons\StoreMigrator\Models\StoreMigratorIntegration;

class StoreMigratorIntegratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect([
            [
				'name' => 'Prestashop Store Inventory',
				'integration' => 'prestashop',
				'resources' => ['categories', 'products'],
			],
        ])->each(function ($migrator) {
            StoreMigratorIntegration::updateOrCreate([
				'name' => $migrator['name'],
			], $migrator);
        });
    }
}
