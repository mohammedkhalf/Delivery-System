<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pro_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->enum('benefit_type', ['discount', 'delivery_fee', 'coupon']);
            $table->double('amount_saved', 24, 3)->default(0);
            $table->double('discount_percentage', 8, 3)->nullable();
            $table->double('max_discount_amount', 24, 3)->nullable();
            $table->double('min_order_amount', 24, 3)->nullable();
            $table->string('delivery_offer_type', 32)->nullable();
            $table->double('delivery_charge_discount_percentage', 8, 3)->nullable();
            $table->double('delivery_fee_reduction_amount', 24, 3)->nullable();
            $table->double('original_delivery_charge', 24, 3)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('user_id');
            $table->index('subscription_id');
            $table->index('transaction_id');
            $table->index('plan_id');
            $table->index('benefit_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pro_discounts');
    }
};
