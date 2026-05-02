<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_scan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_url', 2048);
            $table->string('url_key', 255);
            $table->string('status', 30)->default('pending');
            $table->string('external_job_id', 255)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique('url_key');
            $table->index(['status', 'requested_at']);
            $table->index('external_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_scan_requests');
    }
};
