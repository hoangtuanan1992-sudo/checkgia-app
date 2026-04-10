<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('name');
            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
