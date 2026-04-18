<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopee_product_id')->constrained('shopee_products')->cascadeOnDelete();
            $table->foreignId('shopee_shop_id')->constrained('shopee_shops')->cascadeOnDelete();
            $table->text('url');
            $table->boolean('is_enabled')->default(true);
            $table->integer('price_adjustment')->default(0);
            $table->unsignedBigInteger('last_price')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['shopee_product_id', 'shopee_shop_id']);
            $table->index(['is_enabled', 'last_scraped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_competitors');
    }
};
