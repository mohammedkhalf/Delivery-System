<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The service tax payer is standardized to `service_provider` — the string the Service module's
     * cart and booking read (CalculateTaxService with tax_payer `service_provider`). The tax-setup UI
     * previously stored it as `service`, so rename any existing rows in the tax tables to match.
     */
    public function up(): void
    {
        foreach (['system_tax_setups', 'order_taxes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tax_payer')) {
                DB::table($table)->where('tax_payer', 'service')->update(['tax_payer' => 'service_provider']);
            }
        }
    }

    public function down(): void
    {
        foreach (['system_tax_setups', 'order_taxes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tax_payer')) {
                DB::table($table)->where('tax_payer', 'service_provider')->update(['tax_payer' => 'service']);
            }
        }
    }
};
