<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'grok_api_key')) {
                $table->text('grok_api_key')->nullable()->after('website_scrape_timeout_seconds');
            }
            if (! Schema::hasColumn('app_settings', 'grok_model')) {
                $table->string('grok_model')->nullable()->after('grok_api_key');
            }
            if (! Schema::hasColumn('app_settings', 'grok_models')) {
                $table->json('grok_models')->nullable()->after('grok_model');
            }
            if (! Schema::hasColumn('app_settings', 'gemini_api_key')) {
                $table->text('gemini_api_key')->nullable()->after('grok_models');
            }
            if (! Schema::hasColumn('app_settings', 'gemini_model')) {
                $table->string('gemini_model')->nullable()->after('gemini_api_key');
            }
            if (! Schema::hasColumn('app_settings', 'gemini_models')) {
                $table->json('gemini_models')->nullable()->after('gemini_model');
            }
            if (! Schema::hasColumn('app_settings', 'chatgpt_api_key')) {
                $table->text('chatgpt_api_key')->nullable()->after('gemini_models');
            }
            if (! Schema::hasColumn('app_settings', 'chatgpt_model')) {
                $table->string('chatgpt_model')->nullable()->after('chatgpt_api_key');
            }
            if (! Schema::hasColumn('app_settings', 'chatgpt_models')) {
                $table->json('chatgpt_models')->nullable()->after('chatgpt_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $columns = [
                'grok_api_key',
                'grok_model',
                'grok_models',
                'gemini_api_key',
                'gemini_model',
                'gemini_models',
                'chatgpt_api_key',
                'chatgpt_model',
                'chatgpt_models',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('app_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
