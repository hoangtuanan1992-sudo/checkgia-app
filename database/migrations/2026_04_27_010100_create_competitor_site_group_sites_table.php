<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_site_group_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_site_group_id')->constrained('competitor_site_groups')->cascadeOnDelete();
            $table->foreignId('competitor_site_id')->constrained('competitor_sites')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['competitor_site_group_id', 'competitor_site_id'], 'csg_sites_unique');
            $table->index(['competitor_site_id', 'competitor_site_group_id'], 'csg_sites_site_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_site_group_sites');
    }
};
