<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_scrape_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times')) {
                $table->text('scrape_schedule_times')->nullable()->after('scrape_interval_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_scrape_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_scrape_settings', 'scrape_schedule_times')) {
                $table->dropColumn('scrape_schedule_times');
            }
        });
    }
};
