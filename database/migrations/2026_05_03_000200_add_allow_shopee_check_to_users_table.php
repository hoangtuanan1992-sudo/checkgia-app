<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'allow_shopee_check')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allow_compare_match')) {
                $table->boolean('allow_shopee_check')->default(false)->after('allow_compare_match');
            } elseif (Schema::hasColumn('users', 'product_limit')) {
                $table->boolean('allow_shopee_check')->default(false)->after('product_limit');
            } else {
                $table->boolean('allow_shopee_check')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allow_shopee_check')) {
                $table->dropColumn('allow_shopee_check');
            }
        });
    }
};
