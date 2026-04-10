<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('service_start_date')->nullable()->after('email_canonical');
            $table->date('service_end_date')->nullable()->after('service_start_date');
            $table->text('admin_note')->nullable()->after('service_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['service_start_date', 'service_end_date', 'admin_note']);
        });
    }
};
