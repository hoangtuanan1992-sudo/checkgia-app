<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'windows_agent_api_key')) {
                $table->text('windows_agent_api_key')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings') || ! Schema::hasColumn('app_settings', 'windows_agent_api_key')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('windows_agent_api_key');
        });
    }
};
