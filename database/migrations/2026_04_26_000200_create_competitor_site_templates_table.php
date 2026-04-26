<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_site_templates', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('name')->nullable();
            $table->text('name_xpath')->nullable();
            $table->text('price_xpath')->nullable();
            $table->text('price_regex')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_site_templates');
    }
};
