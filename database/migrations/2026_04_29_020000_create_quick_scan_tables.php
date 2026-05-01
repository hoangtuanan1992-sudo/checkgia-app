<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_scan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('base_url', 2048);
            $table->string('sitemap_url', 2048)->nullable();
            $table->unsignedInteger('found_urls')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('quick_scan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('quick_scan_runs')->cascadeOnDelete();
            $table->string('url_hash', 40);
            $table->string('url', 2048);
            $table->string('name_guess', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->boolean('is_product')->default(true);
            $table->timestamps();

            $table->unique(['run_id', 'url_hash']);
            $table->index(['run_id', 'is_product']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_scan_items');
        Schema::dropIfExists('quick_scan_runs');
    }
};

