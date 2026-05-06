<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_site_templates')) {
            return;
        }

        Schema::table('competitor_site_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('competitor_site_templates', 'use_browser')) {
                $table->boolean('use_browser')->default(false)->after('price_regex');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'name_css')) {
                $table->text('name_css')->nullable()->after('use_browser');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'price_css')) {
                $table->text('price_css')->nullable()->after('name_css');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'price_attribute')) {
                $table->string('price_attribute')->nullable()->after('price_css');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'api_url_template')) {
                $table->text('api_url_template')->nullable()->after('price_attribute');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'api_name_path')) {
                $table->string('api_name_path')->nullable()->after('api_url_template');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'api_price_path')) {
                $table->string('api_price_path')->nullable()->after('api_name_path');
            }
            if (! Schema::hasColumn('competitor_site_templates', 'api_headers')) {
                $table->json('api_headers')->nullable()->after('api_price_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('competitor_site_templates')) {
            return;
        }

        Schema::table('competitor_site_templates', function (Blueprint $table) {
            foreach ([
                'api_headers',
                'api_price_path',
                'api_name_path',
                'api_url_template',
                'price_attribute',
                'price_css',
                'name_css',
                'use_browser',
            ] as $column) {
                if (Schema::hasColumn('competitor_site_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
