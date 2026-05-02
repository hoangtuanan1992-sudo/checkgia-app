<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardCompareMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_match_empty_competitor_links_from_scanner_import_with_ai(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
            'price' => 14000000,
            'product_url' => 'https://my-shop.test/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk',
        ]);
        CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Doi thu',
            'domain' => 'competitor.test',
            'position' => 1,
        ]);
        $candidateUrl = 'https://competitor.test/products/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk';
        $this->insertScannerProduct([
            'external_job_id' => 'scan-1',
            'start_url' => 'https://competitor.test/',
            'product_code' => 'GR-RS780WI-PGV(22)-XK',
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
            'price_value' => 14500000,
            'url' => $candidateUrl,
            'source_url' => 'https://competitor.test/',
        ]);
        AppSetting::query()->create([
            'ai_provider' => 'chatgpt',
            'chatgpt_api_key' => 'sk-test-key',
            'chatgpt_model' => 'gpt-test',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'candidateId' => 1,
                                'matchUrl' => $candidateUrl,
                                'confidence' => 0.94,
                                'reason' => 'same model',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->post(route('dashboard.compare-match.run'), ['mode' => 'empty'])
            ->assertRedirect();

        $competitor = Competitor::query()
            ->where('product_id', $product->id)
            ->where('url', $candidateUrl)
            ->first();

        $this->assertNotNull($competitor);
        $this->assertDatabaseHas('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 14500000,
        ]);
        Http::assertSentCount(1);
    }

    public function test_empty_mode_keeps_existing_competitor_link(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Laptop ABC123',
            'price' => 10000000,
            'product_url' => 'https://my-shop.test/laptop-abc123',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Doi thu',
            'domain' => 'competitor.test',
            'position' => 1,
        ]);
        Competitor::query()->create([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => $site->name,
            'url' => 'https://competitor.test/existing',
        ]);
        $this->insertScannerProduct([
            'external_job_id' => 'scan-2',
            'start_url' => 'https://competitor.test/',
            'product_code' => 'ABC123',
            'name' => 'Laptop ABC123',
            'price_value' => 9000000,
            'url' => 'https://competitor.test/new',
            'source_url' => 'https://competitor.test/',
        ]);
        AppSetting::query()->create([
            'ai_provider' => 'chatgpt',
            'chatgpt_api_key' => 'sk-test-key',
            'chatgpt_model' => 'gpt-test',
        ]);

        Http::fake();

        $this->actingAs($owner)
            ->post(route('dashboard.compare-match.run'), ['mode' => 'empty'])
            ->assertRedirect();

        $this->assertDatabaseHas('competitors', [
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'url' => 'https://competitor.test/existing',
        ]);
        Http::assertNothingSent();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertScannerProduct(array $data): void
    {
        $now = now();
        $jobId = DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => $data['external_job_id'],
            'app' => 'windows-product-scanner',
            'start_url' => $data['start_url'],
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'last_pushed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('scanner_import_products')->insert([
            'scanner_import_job_id' => $jobId,
            'external_id' => sha1($data['url']),
            'external_job_id' => $data['external_job_id'],
            'product_code' => $data['product_code'],
            'name' => $data['name'],
            'price_text' => number_format((int) $data['price_value'], 0, ',', '.').'d',
            'price_value' => $data['price_value'],
            'currency' => 'VND',
            'url' => $data['url'],
            'link' => $data['url'],
            'source_url' => $data['source_url'],
            'url_hash' => sha1($data['url']),
            'source_url_hash' => sha1($data['source_url']),
            'dedupe_hash' => sha1($data['source_url'].'|'.$data['url']),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
