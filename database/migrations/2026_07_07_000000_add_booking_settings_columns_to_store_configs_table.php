<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->boolean('can_edit_booking')->default(false);
            $table->boolean('instant_booking')->default(true);
            $table->boolean('repeat_booking')->default(false);
            $table->boolean('schedule_booking')->default(true);
            $table->boolean('schedule_time_restriction_status')->default(false);
            $table->unsignedInteger('schedule_time_restriction_value')->nullable();
            $table->string('schedule_time_restriction_unit')->default('hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->dropColumn([
                'can_edit_booking',
                'instant_booking',
                'repeat_booking',
                'schedule_booking',
                'schedule_time_restriction_status',
                'schedule_time_restriction_value',
                'schedule_time_restriction_unit',
            ]);
        });
    }
};
