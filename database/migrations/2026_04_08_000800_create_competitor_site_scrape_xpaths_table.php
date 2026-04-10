<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_site_scrape_xpaths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_site_id')->constrained('competitor_sites')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position')->default(0);
            $table->string('xpath');
            $table->timestamps();

            $table->index(['competitor_site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_site_scrape_xpaths');
    }
};
