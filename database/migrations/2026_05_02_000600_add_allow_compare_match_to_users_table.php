<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'allow_compare_match')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'product_limit')) {
                $table->boolean('allow_compare_match')->default(false)->after('product_limit');
            } elseif (Schema::hasColumn('users', 'admin_note')) {
                $table->boolean('allow_compare_match')->default(false)->after('admin_note');
            } else {
                $table->boolean('allow_compare_match')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allow_compare_match')) {
                $table->dropColumn('allow_compare_match');
            }
        });
    }
};
