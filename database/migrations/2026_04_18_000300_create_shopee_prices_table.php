<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopee_item_id')->constrained('shopee_items')->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamp('scraped_at');
            $table->text('raw_text')->nullable();
            $table->timestamps();

            $table->index(['shopee_item_id', 'scraped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_prices');
    }
};
