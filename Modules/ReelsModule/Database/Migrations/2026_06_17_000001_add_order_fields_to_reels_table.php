<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reels', function (Blueprint $table) {
            if (!Schema::hasColumn('reels', 'productable_id')) {
                $table->nullableMorphs('productable');
            }

            if (!Schema::hasColumn('reels', 'order_now_button')) {
                $table->boolean('order_now_button')->default(false);
            }

            if (!Schema::hasColumn('reels', 'order_count')) {
                $table->unsignedBigInteger('order_count')->default(0);
            }

            if (!Schema::hasColumn('reels', 'total_sale_amount')) {
                $table->decimal('total_sale_amount', 12, 4)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reels', function (Blueprint $table) {
            foreach (['order_now_button', 'order_count', 'total_sale_amount'] as $column) {
                if (Schema::hasColumn('reels', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('reels', 'productable_id')) {
                $table->dropMorphs('productable');
            }
        });
    }
};
