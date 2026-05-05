<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('competitors', 'note')) {
            return;
        }

        Schema::table('competitors', function (Blueprint $table) {
            $table->text('note')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('competitors', 'note')) {
            return;
        }

        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
