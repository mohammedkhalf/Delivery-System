<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        ['delivery_men', 'zone_id'],
        ['delivery_men', 'vehicle_id'],
        ['orders', 'order_status'],
        ['orders', 'user_id'],
        ['orders', 'store_id'],
        ['orders', 'delivery_man_id'],
        ['stores', 'vendor_id'],
        ['stores', 'zone_id'],
        ['stores', 'slug'],
        ['stores', 'store_business_model'],
        ['store_configs', 'store_id'],
        ['store_notification_settings', 'store_id'],
        ['store_notification_settings', 'module_type'],
        ['store_wallets', 'vendor_id'],
        ['delivery_histories', 'delivery_man_id'],
        ['delivery_man_wallets', 'delivery_man_id'],
        ['order_transactions', 'order_id'],
        ['order_payments', 'order_id'],
        ['order_details', 'item_id'],
        ['order_details', 'order_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            $name = "{$table}_{$column}_index";
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            if ($this->indexExists($table, $name) || $this->columnIndexed($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column, $name) {
                $t->index($column, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            $name = "{$table}_{$column}_index";
            if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }

    private function columnIndexed(string $table, string $column): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->where('seq_in_index', 1)
            ->exists();
    }
};
