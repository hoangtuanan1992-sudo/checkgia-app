<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_products', function (Blueprint $table) {
            $table->foreignId('lease_agent_id')->nullable()->constrained('shopee_agents')->nullOnDelete()->after('is_enabled');
            $table->string('lease_token', 64)->nullable()->after('lease_agent_id');
            $table->timestamp('lease_expires_at')->nullable()->after('lease_token');
            $table->timestamp('last_assigned_at')->nullable()->after('lease_expires_at');

            $table->index(['lease_expires_at', 'lease_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shopee_products', function (Blueprint $table) {
            $table->dropIndex(['lease_expires_at', 'lease_agent_id']);
            $table->dropConstrainedForeignId('lease_agent_id');
            $table->dropColumn(['lease_token', 'lease_expires_at', 'last_assigned_at']);
        });
    }
};
