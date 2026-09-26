<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_zone', function (Blueprint $table) {
            if (!Schema::hasColumn('module_zone', 'additional_delivery_option_status')) {
                $table->boolean('additional_delivery_option_status')->default(0);
            }
            if (!Schema::hasColumn('module_zone', 'minimum_delivery_time')) {
                $table->integer('minimum_delivery_time')->nullable();
            }
            if (!Schema::hasColumn('module_zone', 'minimum_delivery_charge')) {
                $table->decimal('minimum_delivery_charge', 10, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('module_zone', function (Blueprint $table) {
            $columns = ['additional_delivery_option_status', 'minimum_delivery_time', 'minimum_delivery_charge'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('module_zone', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
