<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->boolean('manage_service_setup')->default(true);
            $table->boolean('show_reviews_provider_panel')->default(true);
            $table->decimal('minimum_booking', 24, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->dropColumn([
                'manage_service_setup',
                'show_reviews_provider_panel',
                'minimum_booking',
            ]);
        });
    }
};
