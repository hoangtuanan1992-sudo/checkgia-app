<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scrape_agent_jobs')) {
            return;
        }

        Schema::table('scrape_agent_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('scrape_agent_jobs', 'completed_by_agent_id')) {
                $table->string('completed_by_agent_id', 128)->nullable()->after('leased_by_agent_id');
                $table->index(['completed_by_agent_id', 'status', 'finished_at'], 'scrape_agent_jobs_completed_agent_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('scrape_agent_jobs') || ! Schema::hasColumn('scrape_agent_jobs', 'completed_by_agent_id')) {
            return;
        }

        Schema::table('scrape_agent_jobs', function (Blueprint $table) {
            $table->dropIndex('scrape_agent_jobs_completed_agent_index');
            $table->dropColumn('completed_by_agent_id');
        });
    }
};
