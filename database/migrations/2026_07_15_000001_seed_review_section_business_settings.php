<?php

use App\Models\BusinessSetting;
use App\Models\DataSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the sidebar review-section toggles (both default to enabled so the Reviews menu keeps
     * showing exactly as before until an admin turns them off). Only newly-created keys get the
     * default; existing values are left untouched.
     *
     *   - review_section          -> core BusinessSetting, gates Reviews for non-service modules
     *                                (Admin > Business Settings > Store settings).
     *   - service_review_section  -> service DataSetting (SERVICE_BUSINESS_SETTINGS), gates Reviews
     *                                for the service module (Admin > Business Settings > Providers & Serviceman).
     */
    public function up(): void
    {
        $reviewSection = BusinessSetting::firstOrNew(['key' => 'review_section']);
        if (! $reviewSection->exists) {
            $reviewSection->value = '1';
            $reviewSection->save();
        }

        $serviceReviewSection = DataSetting::firstOrNew([
            'key' => 'service_review_section',
            'type' => SERVICE_BUSINESS_SETTINGS,
        ]);
        if (! $serviceReviewSection->exists) {
            $serviceReviewSection->value = '1';
            $serviceReviewSection->save();
        }
    }

    public function down(): void
    {
        BusinessSetting::where('key', 'review_section')->delete();
        DataSetting::where('type', SERVICE_BUSINESS_SETTINGS)
            ->where('key', 'service_review_section')
            ->delete();
    }
};
