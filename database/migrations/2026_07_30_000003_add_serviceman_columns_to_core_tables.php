<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_notifications') && ! Schema::hasColumn('user_notifications', 'serviceman_id')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('serviceman_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('delivery_men') && Schema::hasColumn('delivery_men', 'is_serviceman')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->dropColumn('is_serviceman');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_notifications', 'serviceman_id')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->dropColumn('serviceman_id');
            });
        }

        if (Schema::hasTable('delivery_men') && ! Schema::hasColumn('delivery_men', 'is_serviceman')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->boolean('is_serviceman')->default(0);
            });
        }
    }
};
