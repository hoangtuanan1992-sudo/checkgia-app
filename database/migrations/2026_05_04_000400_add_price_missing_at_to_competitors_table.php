<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            if (! Schema::hasColumn('competitors', 'price_missing_at')) {
                $table->timestamp('price_missing_at')->nullable()->after('price_adjustment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            if (Schema::hasColumn('competitors', 'price_missing_at')) {
                $table->dropColumn('price_missing_at');
            }
        });
    }
};
