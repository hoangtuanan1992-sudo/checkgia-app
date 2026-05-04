<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_scrape_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_enabled')) {
                $table->boolean('auto_delete_failed_products_enabled')->default(false)->after('scrape_schedule_times');
            }

            if (! Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_days')) {
                $table->unsignedSmallInteger('auto_delete_failed_products_days')->default(7)->after('auto_delete_failed_products_enabled');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'own_scrape_failed_since')) {
                $table->timestamp('own_scrape_failed_since')->nullable()->after('last_scraped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'own_scrape_failed_since')) {
                $table->dropColumn('own_scrape_failed_since');
            }
        });

        Schema::table('user_scrape_settings', function (Blueprint $table) {
            if (Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_days')) {
                $table->dropColumn('auto_delete_failed_products_days');
            }

            if (Schema::hasColumn('user_scrape_settings', 'auto_delete_failed_products_enabled')) {
                $table->dropColumn('auto_delete_failed_products_enabled');
            }
        });
    }
};
