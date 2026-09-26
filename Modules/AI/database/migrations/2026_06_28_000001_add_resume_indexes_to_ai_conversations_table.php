<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            // The per-turn auto-resume lookup and the recent-conversations list
            // both filter by owner + module + zone + status and sort by
            // updated_at. Only single-column indexes on user_id/guest_id existed,
            // so those queries scanned. Two composites (user_id and guest_id are
            // alternatives) cover both access paths.
            $table->index(['user_id', 'status', 'module_id', 'zone_id', 'updated_at'], 'ai_conv_user_resume_idx');
            $table->index(['guest_id', 'status', 'module_id', 'zone_id', 'updated_at'], 'ai_conv_guest_resume_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex('ai_conv_user_resume_idx');
            $table->dropIndex('ai_conv_guest_resume_idx');
        });
    }
};
