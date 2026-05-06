<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_scrape_settings') || Schema::hasColumn('user_scrape_settings', 'scrape_priority')) {
            return;
        }

        Schema::table('user_scrape_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('scrape_priority')->default(50)->after('scrape_schedule_times');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_scrape_settings') || ! Schema::hasColumn('user_scrape_settings', 'scrape_priority')) {
            return;
        }

        Schema::table('user_scrape_settings', function (Blueprint $table) {
            $table->dropColumn('scrape_priority');
        });
    }
};
