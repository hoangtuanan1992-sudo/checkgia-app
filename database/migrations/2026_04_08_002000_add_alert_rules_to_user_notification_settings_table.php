<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_settings', function (Blueprint $table) {
            $table->unsignedInteger('alert_competitor_cheaper_percent')->nullable()->after('telegram_chat_id');
            $table->unsignedBigInteger('alert_competitor_drop_amount')->nullable()->after('alert_competitor_cheaper_percent');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['alert_competitor_cheaper_percent', 'alert_competitor_drop_amount']);
        });
    }
};
