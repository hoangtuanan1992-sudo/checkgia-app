<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'product_limit')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'admin_note')) {
                $table->unsignedInteger('product_limit')->default(100)->after('admin_note');
            } elseif (Schema::hasColumn('users', 'service_end_date')) {
                $table->unsignedInteger('product_limit')->default(100)->after('service_end_date');
            } else {
                $table->unsignedInteger('product_limit')->default(100);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'product_limit')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('product_limit');
        });
    }
};
