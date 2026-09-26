<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_id')->nullable();
            $table->unsignedInteger('module_id');
            $table->string('zone_id');
            $table->unsignedInteger('result_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module_id', 'zone_id', 'created_at']);
            $table->index(['keyword', 'module_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
