<?php

use App\CentralLogics\Helpers;
use App\Models\Module;
use App\Models\ModuleWiseBanner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{

    public function up(): void
    {
        $moduleIds = Module::whereIn('module_type', ['grocery', 'food', 'ecommerce', 'pharmacy', 'service'])->pluck('id');

        ModuleWiseBanner::whereIn('module_id', $moduleIds)
            ->whereIn('key', ['best_reviewed_section_banner', 'basic_section_nearby', 'new_arrival_section_banner'])
            ->get()
            ->each(function (ModuleWiseBanner $banner) {
                Helpers::check_and_delete('promotional_banner/', $banner->getRawOriginal('value'));
                $banner->storage()->delete();
                $banner->translations()->delete();
                $banner->delete();
            });
    }

    public function down(): void
    {
    }
};
