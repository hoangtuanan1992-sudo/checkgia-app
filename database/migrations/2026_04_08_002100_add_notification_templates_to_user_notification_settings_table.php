<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_settings', function (Blueprint $table) {
            $table->boolean('notify_all_price_changes')->default(false)->after('alert_competitor_drop_amount');
            $table->string('notify_all_price_changes_title')->nullable()->after('notify_all_price_changes');
            $table->text('notify_all_price_changes_body')->nullable()->after('notify_all_price_changes_title');
            $table->string('alert_cheaper_title')->nullable()->after('notify_all_price_changes_body');
            $table->text('alert_cheaper_body')->nullable()->after('alert_cheaper_title');
            $table->string('alert_drop_title')->nullable()->after('alert_cheaper_body');
            $table->text('alert_drop_body')->nullable()->after('alert_drop_title');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_all_price_changes',
                'notify_all_price_changes_title',
                'notify_all_price_changes_body',
                'alert_cheaper_title',
                'alert_cheaper_body',
                'alert_drop_title',
                'alert_drop_body',
            ]);
        });
    }
};
