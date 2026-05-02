<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compare_match_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('processed_products')->default(0);
            $table->unsignedInteger('total_cells')->default(0);
            $table->unsignedInteger('processed_cells')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('skipped_existing_count')->default(0);
            $table->unsignedInteger('no_candidates_count')->default(0);
            $table->unsignedInteger('no_match_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('current_product_name')->nullable();
            $table->text('message')->nullable();
            $table->json('error_samples')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('compare_match_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compare_match_run_id')->constrained('compare_match_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competitor_site_id')->nullable()->constrained('competitor_sites')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('matched_url', 2048)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['compare_match_run_id', 'status', 'id'], 'compare_match_items_run_status_id_index');
            $table->index(['compare_match_run_id', 'product_id'], 'compare_match_items_run_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compare_match_run_items');
        Schema::dropIfExists('compare_match_runs');
    }
};
