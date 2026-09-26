<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->double('pro_discount', 23, 3)->default(0);
            $table->double('pro_delivery_discount', 23, 3)->default(0);
        });

        if (Schema::hasTable('order_pro_discounts')) {
            DB::statement("
                UPDATE order_transactions ot
                INNER JOIN order_pro_discounts opd ON opd.order_id = ot.order_id
                SET ot.pro_discount = COALESCE(opd.amount_saved, 0),
                    ot.pro_delivery_discount = COALESCE(opd.delivery_fee_reduction_amount, 0)
            ");
        }
    }

    public function down(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['pro_discount', 'pro_delivery_discount']);
        });
    }
};
