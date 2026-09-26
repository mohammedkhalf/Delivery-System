<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_order_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->unsignedInteger('module_id')->nullable();
            $table->string('module_type', 50)->nullable();
            $table->string('zone_id', 255)->nullable();
            $table->date('remind_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['remind_at', 'status']);
            $table->index(['user_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_order_reminders');
    }
};
