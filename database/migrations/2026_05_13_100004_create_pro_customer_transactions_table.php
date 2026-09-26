<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_customer_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('transaction_reference', 191)->nullable();
            $table->string('plan_name', 191);
            $table->enum('plan_type', ['free_trial', 'paid'])->default('paid');
            $table->double('plan_price', 24, 3)->default(0);
            $table->double('amount', 24, 3)->default(0);
            $table->string('payment_method', 100)->nullable();
            $table->enum('payment_status', ['success', 'pending', 'failed', 'refunded'])->default('pending');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('subscription_id');
            $table->index('plan_id');
            $table->index('transaction_reference');
            $table->index('payment_method');
            $table->index('payment_status');
            $table->index('paid_at');
        });

        DB::statement('ALTER TABLE pro_customer_transactions AUTO_INCREMENT = 10000');
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_transactions');
    }
};
