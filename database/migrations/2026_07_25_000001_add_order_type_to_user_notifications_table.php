<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_notifications', 'order_type')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->string('order_type')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_notifications', 'order_type')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->dropColumn('order_type');
            });
        }
    }
};
