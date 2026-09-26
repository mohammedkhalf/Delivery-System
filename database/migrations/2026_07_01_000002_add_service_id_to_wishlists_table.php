<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a dedicated nullable `service_id` discriminator so the shared
     * wishlists table can hold Service favorites alongside the existing
     * item (product) and store favorites, mirroring the `product_id`
     * convention already used for new-module products. A wishlist row now
     * targets exactly one of item_id / store_id / service_id.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (! Schema::hasColumn('wishlists', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable();
                $table->index('service_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (Schema::hasColumn('wishlists', 'service_id')) {
                $table->dropIndex(['service_id']);
                $table->dropColumn('service_id');
            }
        });
    }
};
