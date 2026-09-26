<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_preference_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->json('top_items')->nullable();
            $table->json('top_categories')->nullable();
            $table->json('top_stores')->nullable();
            $table->json('ai_keywords')->nullable();
            $table->unsignedInteger('update_count')->default(0);
            $table->timestamp('last_rebuilt_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_id'], 'cps_user_module_unique');
            $table->index('update_count', 'cps_update_count_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_preference_summaries');
    }
};
