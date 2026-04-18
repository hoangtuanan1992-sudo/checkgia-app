<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('shopee_enabled')->default(false)->after('demo_user_id');
            $table->text('shopee_extension_token')->nullable()->after('shopee_enabled');
            $table->unsignedInteger('shopee_scrape_interval_seconds')->default(300)->after('shopee_extension_token');
            $table->unsignedInteger('shopee_rest_seconds_min')->default(5)->after('shopee_scrape_interval_seconds');
            $table->unsignedInteger('shopee_rest_seconds_max')->default(15)->after('shopee_rest_seconds_min');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'shopee_enabled',
                'shopee_extension_token',
                'shopee_scrape_interval_seconds',
                'shopee_rest_seconds_min',
                'shopee_rest_seconds_max',
            ]);
        });
    }
};
