<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reel_engagements', function (Blueprint $table) {
            if (!Schema::hasColumn('reel_engagements', 'amount')) {
                $table->decimal('amount', 12, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reel_engagements', function (Blueprint $table) {
            if (Schema::hasColumn('reel_engagements', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
};
