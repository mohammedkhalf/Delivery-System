<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_zone_delivery_options')) {
            return;
        }

        Schema::create('module_zone_delivery_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id');
            $table->foreignId('zone_id');
            $table->enum('delivery_type', ['standard', 'express', 'slightly_delay'])->default('standard');
            $table->decimal('extra_charge', 10, 4)->nullable();
            $table->decimal('reduce_charge', 10, 4)->nullable();
            $table->integer('add_delivery_time')->nullable();
            $table->integer('reduce_delivery_time')->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'zone_id', 'delivery_type'], 'mzdo_module_zone_type_unique');
            $table->index(['module_id', 'zone_id'], 'mzdo_module_zone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_zone_delivery_options');
    }
};
