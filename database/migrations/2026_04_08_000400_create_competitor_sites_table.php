<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('competitors', function (Blueprint $table) {
            $table->foreignId('competitor_site_id')
                ->nullable()
                ->after('product_id')
                ->constrained('competitor_sites')
                ->nullOnDelete();
        });

        $pairs = DB::table('competitors')
            ->join('products', 'products.id', '=', 'competitors.product_id')
            ->select('products.user_id', 'competitors.name')
            ->distinct()
            ->orderBy('products.user_id')
            ->get();

        if ($pairs->isNotEmpty()) {
            $rows = $pairs->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name' => $r->name,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DB::table('competitor_sites')->upsert($rows, ['user_id', 'name'], ['updated_at']);

            $sites = DB::table('competitor_sites')
                ->select('id', 'user_id', 'name')
                ->get()
                ->mapWithKeys(fn ($s) => [$s->user_id.'|'.$s->name => $s->id]);

            $competitors = DB::table('competitors')
                ->join('products', 'products.id', '=', 'competitors.product_id')
                ->select('competitors.id as competitor_id', 'products.user_id', 'competitors.name')
                ->orderBy('competitors.id')
                ->get();

            foreach ($competitors as $c) {
                $key = $c->user_id.'|'.$c->name;
                $siteId = $sites[$key] ?? null;

                if ($siteId) {
                    DB::table('competitors')
                        ->where('id', $c->competitor_id)
                        ->update(['competitor_site_id' => $siteId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competitor_site_id');
        });

        Schema::dropIfExists('competitor_sites');
    }
};
