<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('preference_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('score', 10, 2)->default(0);
            $table->unsignedBigInteger('module_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'preference_type', 'reference_id', 'module_id'], 'cp_user_type_ref_module_unique');
            $table->index(['user_id', 'preference_type', 'module_id', 'score'], 'cp_user_type_module_score_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_preferences');
    }
};
