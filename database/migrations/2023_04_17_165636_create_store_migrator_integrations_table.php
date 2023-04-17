<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('xtend_store_migrator_integrations', function (Blueprint $table) {
            $table->id();
			$table->string('name');
            $table->string('integration');
			$table->json('resources')->nullable();
			$table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('xtend_store_migrator_integrations');
    }
};
