<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_key')->unique();
            $table->string('name')->nullable();
            $table->string('version')->nullable();
            $table->string('platform')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('mode')->default('all');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_scrape_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'mode', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_agents');
    }
};
