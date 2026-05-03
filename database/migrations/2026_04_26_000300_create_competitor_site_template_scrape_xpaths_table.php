<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competitor_site_template_scrape_xpaths')) {
            return;
        }

        Schema::create('competitor_site_template_scrape_xpaths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_site_template_id')->constrained('competitor_site_templates')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position')->default(0);
            $table->text('xpath');
            $table->timestamps();

            $table->index(['competitor_site_template_id', 'type'], 'cstx_template_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_site_template_scrape_xpaths');
    }
};
