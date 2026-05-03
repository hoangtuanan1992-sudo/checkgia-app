<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'visible_product_group_ids')) {
                $table->text('visible_product_group_ids')->nullable()->after('parent_user_id');
            }

            if (! Schema::hasColumn('users', 'visible_competitor_site_group_ids')) {
                $table->text('visible_competitor_site_group_ids')->nullable()->after('visible_product_group_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'visible_competitor_site_group_ids')) {
                $table->dropColumn('visible_competitor_site_group_ids');
            }

            if (Schema::hasColumn('users', 'visible_product_group_ids')) {
                $table->dropColumn('visible_product_group_ids');
            }
        });
    }
};
