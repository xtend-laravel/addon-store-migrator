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
        Schema::create('xtend_store_migrator_resource_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id');
			$table->morphs('model', 'store_migrator_model_index');
	        $table->enum('status', ['pending', 'created', 'updated', 'deleted'])->default('pending');
			$table->timestamp('failed_at')->nullable();
			$table->string('failed_reason')->nullable();
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
        Schema::dropIfExists('xtend_store_migrator_resource_models');
    }
};
