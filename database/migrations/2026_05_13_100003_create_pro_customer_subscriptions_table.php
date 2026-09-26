<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('plan_name', 191);
            $table->enum('plan_type', ['free_trial', 'paid'])->default('paid');
            $table->double('plan_price', 24, 3)->default(0);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['active', 'expired', 'canceled'])->default('active');
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('plan_id');
            $table->index('end_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_subscriptions');
    }
};
