<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            if (! Schema::hasColumn('competitors', 'variant_key')) {
                $table->string('variant_key')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'variant_name')) {
                $table->string('variant_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            if (Schema::hasColumn('competitors', 'variant_key')) {
                $table->dropColumn('variant_key');
            }
            if (Schema::hasColumn('competitors', 'variant_name')) {
                $table->dropColumn('variant_name');
            }
        });
    }
};
