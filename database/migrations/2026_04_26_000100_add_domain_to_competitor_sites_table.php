<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('name');
            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'domain']);
            $table->dropColumn('domain');
        });
    }
};
