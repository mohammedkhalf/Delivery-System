<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Maps permission keys from the previously released scheme onto the current
 * group-wise scheme, so upgrading installs keep the access their roles already had.
 *
 * Only keys that shipped in a prior release appear here.
 */
return new class extends Migration
{
    /** Retired released key => key that now covers it. A key may map to several. */
    private const RENAMED = [
        'zone'                  => ['settings'],
        'apps_setting'          => ['system_config'],
        'systems_addons'        => ['system_config'],
        'contact_messages'      => ['customer_management'],
        'vehicle_category'      => ['deliveryman'],
        'fleet_view'            => ['heat_map'],
        'subscription_management' => ['subscription'],
        'attribute'             => ['category'],
        'unit'                  => ['category'],
        'brand'                 => ['category'],
        'common_condition'      => ['category'],
        'advertisement'         => ['coupon'],
        'trip_tax_report'       => ['admin_text_module'],
        'provider_vat_reports'  => ['vendor_vat_report'],
        'ride_report'           => ['report', 'sales_report', 'earning_report'],
        'employee_role'         => ['employee'],
        'transaction_report'    => ['report'],
        'rental_report'         => ['sales_report'],
        'trip_reports'          => ['sales_report'],
        'provider_wise_report'  => ['performance_report'],
        'vehicle_reports'       => ['performance_report'],
        'page_social_management' => ['social_media', 'landing_pages', 'business_pages', 'seo'],
        'p_addons'              => ['addon'],
        'push_ntf'              => ['notification'],
    ];

    /** Still-current key => new sibling keys split out of it. */
    private const SPLIT = [
        'report'             => ['sales_report', 'performance_report', 'earning_report', 'expense_report',
                                 'disbursement_report', 'vendor_vat_report', 'admin_text_module'],
        'settings'           => ['system_tax', 'system_config', 'login_setup', 'email_setups', 'notification_setup',
                                 'third_party-ms', 'ride_settings', 'service_settings', 'gallery', 'clean_database',
                                 'withdraw_method', 'social_media', 'landing_pages', 'business_pages', 'seo'],
        'customer_management' => ['customer_wallet', 'customer_loyalty_point', 'user_overview'],
        'order'              => ['dispatch'],
        'rider'              => ['rider_level', 'rider_review'],
        'email_setups'       => ['notification_setup'],
        'service_management' => ['service_settings'],
        'store'              => ['store_bulk'],
        'vehicle'            => ['rental_vehicle_setup'],
        'provider'           => ['rental_provider_bulk'],
        'promotion'          => ['rental_banners', 'rental_communication'],
    ];

    public function up(): void
    {
        $this->remapAdminRoles();
        $this->backfillEmployeeCustomWebsite();
        $this->backfillEmployeeRoleForModuleType('rental', 'report');
        $this->backfillEmployeeRoleForModuleType('service', 'reviews');
    }

    private function remapAdminRoles(): void
    {
        DB::table('admin_roles')->select('id', 'modules')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $modules = (array) json_decode($role->modules ?? '[]', true);
                $updated = $modules;

                foreach (self::RENAMED as $old => $replacements) {
                    if (in_array($old, $modules)) {
                        $updated = array_merge($updated, $replacements);
                    }
                }

                foreach (self::SPLIT as $key => $children) {
                    if (in_array($key, $modules)) {
                        $updated = array_merge($updated, $children);
                    }
                }

                $updated = array_values(array_diff(array_unique($updated), array_keys(self::RENAMED)));

                if ($updated !== $modules) {
                    DB::table('admin_roles')->where('id', $role->id)->update(['modules' => json_encode($updated)]);
                }
            }
        });
    }

    private function backfillEmployeeCustomWebsite(): void
    {
        DB::table('employee_roles')->select('id', 'modules')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $modules = (array) json_decode($role->modules ?? '[]', true);

                if (in_array('custom_website', $modules)) {
                    continue;
                }

                $modules[] = 'custom_website';
                DB::table('employee_roles')->where('id', $role->id)->update(['modules' => json_encode(array_values($modules))]);
            }
        });
    }

    private function backfillEmployeeRoleForModuleType(string $moduleType, string $key): void
    {
        $roleIds = DB::table('employee_roles')
            ->join('stores', 'stores.id', '=', 'employee_roles.store_id')
            ->join('modules', 'modules.id', '=', 'stores.module_id')
            ->where('modules.module_type', $moduleType)
            ->pluck('employee_roles.id');

        DB::table('employee_roles')->whereIn('id', $roleIds)->select('id', 'modules')->orderBy('id')
            ->get()
            ->each(function ($role) use ($key) {
                $modules = (array) json_decode($role->modules ?? '[]', true);

                if (in_array($key, $modules)) {
                    return;
                }

                $modules[] = $key;
                DB::table('employee_roles')->where('id', $role->id)->update(['modules' => json_encode(array_values($modules))]);
            });
    }

    public function down(): void
    {
    }
};
