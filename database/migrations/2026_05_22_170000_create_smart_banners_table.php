<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->enum('active_days', ['everyday', 'custom_date'])->default('custom_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('position', 32)->default('top');
            $table->enum('redirect_type', ['category', 'module_home', 'store_page', 'offer_page'])->default('category');
            $table->unsignedBigInteger('redirect_target_id')->nullable();
            $table->string('image', 191)->nullable();
            $table->boolean('status')->default(true);
            $table->string('created_by', 32)->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'status']);
            $table->index(['zone_id', 'position', 'start_date', 'end_date']);
            $table->index(['redirect_type', 'redirect_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_banners');
    }
};
