<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_pro_discounts')) {
            return;
        }

        if (! Schema::hasColumn('order_pro_discounts', 'service_booking_id')) {
            Schema::table('order_pro_discounts', function (Blueprint $table) {
                $table->unsignedBigInteger('service_booking_id')->nullable();
                $table->index('service_booking_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_pro_discounts', 'service_booking_id')) {
            return;
        }

        Schema::table('order_pro_discounts', function (Blueprint $table) {
            $table->dropIndex(['service_booking_id']);
            $table->dropColumn('service_booking_id');
        });
    }
};
