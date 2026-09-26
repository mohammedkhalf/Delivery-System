<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_type')) {
                $table->string('delivery_type', 32)->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_type_charge')) {
                $table->decimal('delivery_type_charge', 10, 4)->default(0);
            }
        });

        if (!Schema::hasIndex('orders', 'orders_delivery_type_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('delivery_type', 'orders_delivery_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('orders', 'orders_delivery_type_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_delivery_type_idx');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_type_charge')) {
                $table->dropColumn('delivery_type_charge');
            }
            if (Schema::hasColumn('orders', 'delivery_type')) {
                $table->dropColumn('delivery_type');
            }
        });
    }
};
