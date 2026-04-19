<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_agents', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('is_enabled');
            $table->string('pair_code')->nullable()->after('is_approved');
            $table->text('api_token')->nullable()->after('pair_code');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_agents', function (Blueprint $table) {
            $table->dropColumn(['is_approved', 'pair_code', 'api_token']);
        });
    }
};
