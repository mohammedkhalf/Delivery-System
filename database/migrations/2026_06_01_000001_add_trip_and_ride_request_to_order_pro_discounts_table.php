<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_pro_discounts', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('ride_request_id')->nullable();

            $table->index('trip_id');
            $table->index('ride_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_pro_discounts', function (Blueprint $table) {
            $table->dropIndex(['trip_id']);
            $table->dropIndex(['ride_request_id']);
            $table->dropColumn(['trip_id', 'ride_request_id']);
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};
