<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_customer_benefit_settings', function (Blueprint $table) {
            $table->id();
            $table->string('benefit_type', 50);
            $table->string('module_type', 50)->nullable();
            $table->json('settings');
            $table->timestamps();

            $table->unique(['benefit_type', 'module_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_benefit_settings');
    }
};
