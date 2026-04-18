<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_own')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_own', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_shops');
    }
};
