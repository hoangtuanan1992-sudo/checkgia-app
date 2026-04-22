<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('website_scrape_batch_per_minute')->nullable()->after('demo_user_id');
            $table->unsignedInteger('website_scrape_concurrency')->nullable()->after('website_scrape_batch_per_minute');
            $table->unsignedInteger('website_scrape_timeout_seconds')->nullable()->after('website_scrape_concurrency');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'website_scrape_batch_per_minute',
                'website_scrape_concurrency',
                'website_scrape_timeout_seconds',
            ]);
        });
    }
};
