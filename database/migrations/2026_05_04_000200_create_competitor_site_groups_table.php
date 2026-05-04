<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_site_groups')) {
            Schema::create('competitor_site_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['user_id', 'name']);
            });
        }

        if (! Schema::hasTable('competitor_site_group_sites')) {
            Schema::create('competitor_site_group_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competitor_site_group_id')
                    ->constrained('competitor_site_groups')
                    ->cascadeOnDelete();
                $table->foreignId('competitor_site_id')
                    ->constrained('competitor_sites')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['competitor_site_group_id', 'competitor_site_id'], 'competitor_group_site_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_site_group_sites');
        Schema::dropIfExists('competitor_site_groups');
    }
};
