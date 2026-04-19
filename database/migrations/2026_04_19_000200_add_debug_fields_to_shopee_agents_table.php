<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_agents', function (Blueprint $table) {
            $table->text('last_error')->nullable()->after('api_token');
            $table->text('last_task_url')->nullable()->after('last_error');
            $table->timestamp('last_report_at')->nullable()->after('last_task_url');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_agents', function (Blueprint $table) {
            $table->dropColumn(['last_error', 'last_task_url', 'last_report_at']);
        });
    }
};
