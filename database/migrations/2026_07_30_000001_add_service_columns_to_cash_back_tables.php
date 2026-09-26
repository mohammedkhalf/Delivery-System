<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_backs') && ! Schema::hasColumn('cash_backs', 'is_service')) {
            Schema::table('cash_backs', function (Blueprint $table) {
                $table->boolean('is_service')->default(false);
            });
        }

        if (Schema::hasTable('cash_back_histories') && ! Schema::hasColumn('cash_back_histories', 'service_booking_id')) {
            Schema::table('cash_back_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('service_booking_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_backs', 'is_service')) {
            Schema::table('cash_backs', function (Blueprint $table) {
                $table->dropColumn('is_service');
            });
        }

        if (Schema::hasColumn('cash_back_histories', 'service_booking_id')) {
            Schema::table('cash_back_histories', function (Blueprint $table) {
                $table->dropColumn('service_booking_id');
            });
        }
    }
};
