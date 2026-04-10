<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();
            $table->index(['competitor_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_prices');
    }
};
