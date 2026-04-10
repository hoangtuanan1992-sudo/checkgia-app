<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->string('name_xpath')->nullable()->after('name');
            $table->string('price_xpath')->nullable()->after('name_xpath');
            $table->string('price_regex')->nullable()->after('price_xpath');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_sites', function (Blueprint $table) {
            $table->dropColumn(['name_xpath', 'price_xpath', 'price_regex']);
        });
    }
};
