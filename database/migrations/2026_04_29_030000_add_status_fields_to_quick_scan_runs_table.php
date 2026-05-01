<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_scan_runs', function (Blueprint $table) {
            $table->string('status', 20)->default('idle')->after('found_urls');
            $table->boolean('stop_requested')->default(false)->after('status');
            $table->unsignedInteger('processed_count')->default(0)->after('stop_requested');
            $table->unsignedInteger('product_count')->default(0)->after('processed_count');
            $table->unsignedInteger('priced_count')->default(0)->after('product_count');
            $table->timestamp('started_at')->nullable()->after('priced_count');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('quick_scan_runs', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'stop_requested',
                'processed_count',
                'product_count',
                'priced_count',
                'started_at',
                'finished_at',
            ]);
        });
    }
};

