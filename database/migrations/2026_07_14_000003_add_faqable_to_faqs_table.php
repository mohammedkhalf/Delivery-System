<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('f_a_q_s') && ! Schema::hasColumn('f_a_q_s', 'faqable_id')) {
            Schema::table('f_a_q_s', function (Blueprint $table) {
                $table->nullableMorphs('faqable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('f_a_q_s') && Schema::hasColumn('f_a_q_s', 'faqable_id')) {
            Schema::table('f_a_q_s', function (Blueprint $table) {
                $table->dropMorphs('faqable');
            });
        }
    }
};
