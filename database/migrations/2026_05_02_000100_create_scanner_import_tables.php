<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('external_job_id', 255);
            $table->string('app', 100)->nullable();
            $table->string('start_url', 2048)->nullable();
            $table->string('mode', 50)->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('imported_product_count')->default(0);
            $table->unsignedInteger('priced_product_count')->default(0);
            $table->unsignedInteger('batch_index')->nullable();
            $table->unsignedInteger('batch_total')->nullable();
            $table->unsignedInteger('last_batch_size')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->json('raw_source')->nullable();
            $table->timestamps();

            $table->unique('external_job_id');
            $table->index('last_pushed_at');
        });

        Schema::create('scanner_import_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scanner_import_job_id')->constrained('scanner_import_jobs')->cascadeOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('external_job_id', 255)->nullable();
            $table->string('product_code', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('price_text', 255)->nullable();
            $table->unsignedBigInteger('price_value')->nullable();
            $table->string('currency', 20)->nullable();
            $table->string('url', 2048);
            $table->string('link', 2048)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('url_hash', 40);
            $table->string('source_url_hash', 40)->nullable();
            $table->string('dedupe_hash', 40);
            $table->timestamp('imported_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['scanner_import_job_id', 'dedupe_hash'], 'scanner_import_products_job_dedupe_unique');
            $table->index(['scanner_import_job_id', 'price_value']);
            $table->index('external_job_id');
            $table->index('product_code');
            $table->index('url_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_import_products');
        Schema::dropIfExists('scanner_import_jobs');
    }
};
