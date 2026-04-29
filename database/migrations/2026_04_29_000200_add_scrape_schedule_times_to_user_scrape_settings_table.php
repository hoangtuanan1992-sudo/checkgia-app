<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_scrape_settings', function (Blueprint $table) {
            $table->text('scrape_schedule_times')->nullable()->after('scrape_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('user_scrape_settings', function (Blueprint $table) {
            $table->dropColumn('scrape_schedule_times');
        });
    }
};

