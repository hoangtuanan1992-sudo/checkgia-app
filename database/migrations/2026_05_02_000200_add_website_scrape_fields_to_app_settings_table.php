<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'website_scrape_batch_per_minute')) {
                $table->unsignedInteger('website_scrape_batch_per_minute')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'website_scrape_concurrency')) {
                $table->unsignedInteger('website_scrape_concurrency')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'website_scrape_timeout_seconds')) {
                $table->unsignedInteger('website_scrape_timeout_seconds')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $columns = [
                'website_scrape_batch_per_minute',
                'website_scrape_concurrency',
                'website_scrape_timeout_seconds',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('app_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
