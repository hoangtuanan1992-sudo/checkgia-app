<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_products', function (Blueprint $table) {
            $table->string('own_variant_path', 50)->nullable()->after('own_url');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_products', function (Blueprint $table) {
            $table->dropColumn('own_variant_path');
        });
    }
};
