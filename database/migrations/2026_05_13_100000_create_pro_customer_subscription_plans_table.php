<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_customer_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name', 191);
            $table->enum('plan_type', ['free_trial', 'paid'])->default('paid');
            $table->double('price', 24, 3)->default(0);
            $table->integer('duration')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_subscription_plans');
    }
};
