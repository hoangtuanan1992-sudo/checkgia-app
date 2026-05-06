<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id', 128)->unique();
            $table->string('name')->nullable();
            $table->string('version', 50)->nullable();
            $table->string('status', 30)->default('offline');
            $table->json('capabilities')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_lease_at')->nullable();
            $table->timestamp('last_result_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scrape_agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_uuid', 64)->unique();
            $table->string('target_key', 80)->unique();
            $table->string('type', 30);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competitor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competitor_site_id')->nullable()->constrained('competitor_sites')->nullOnDelete();
            $table->string('url', 2048);
            $table->string('domain', 255)->nullable();
            $table->string('variant_key')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('leased_by_agent_id', 128)->nullable();
            $table->string('lease_token', 80)->nullable();
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->text('last_error')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at', 'priority'], 'scrape_agent_jobs_ready_index');
            $table->index(['leased_by_agent_id', 'status'], 'scrape_agent_jobs_agent_status_index');
            $table->index(['lease_expires_at', 'status'], 'scrape_agent_jobs_lease_expiry_index');
            $table->index(['product_id', 'status'], 'scrape_agent_jobs_product_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_agent_jobs');
        Schema::dropIfExists('scrape_agents');
    }
};
