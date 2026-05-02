<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\CompareMatchRun;
use App\Models\CompareMatchRunItem;
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
        $owner = User::factory()->create(['role' => 'owner', 'allow_compare_match' => true]);
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

        $start = $this->actingAs($owner)
            ->postJson(route('dashboard.compare-match.run'), ['mode' => 'empty'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.totalCells', 1)
            ->assertJsonPath('run.processedCells', 0);

        $runId = (int) $start->json('run.id');

        $this->actingAs($owner)
            ->postJson(route('dashboard.compare-match.tick', $runId))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.status', 'done')
            ->assertJsonPath('run.processedCells', 1)
            ->assertJsonPath('run.remainingCells', 0)
            ->assertJsonPath('run.matched', 1);

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
        $owner = User::factory()->create(['role' => 'owner', 'allow_compare_match' => true]);
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
            ->postJson(route('dashboard.compare-match.run'), ['mode' => 'empty'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.status', 'done')
            ->assertJsonPath('run.totalCells', 0)
            ->assertJsonPath('run.skippedExisting', 1);

        $this->assertDatabaseHas('competitors', [
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'url' => 'https://competitor.test/existing',
        ]);
        Http::assertNothingSent();
    }

    public function test_empty_skip_checked_mode_skips_blank_cells_that_were_checked_before(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'allow_compare_match' => true]);
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
        $previousRun = CompareMatchRun::query()->create([
            'user_id' => $owner->id,
            'mode' => 'empty',
            'status' => 'done',
            'total_products' => 1,
            'processed_products' => 1,
            'total_cells' => 1,
            'processed_cells' => 1,
        ]);
        CompareMatchRunItem::query()->create([
            'compare_match_run_id' => $previousRun->id,
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'status' => 'no_match',
            'processed_at' => now(),
        ]);
        $this->insertScannerProduct([
            'external_job_id' => 'scan-3',
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
            ->postJson(route('dashboard.compare-match.run'), ['mode' => 'empty_skip_checked'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.status', 'done')
            ->assertJsonPath('run.totalCells', 0);

        Http::assertNothingSent();
    }

    public function test_compare_match_button_is_hidden_until_admin_enables_it(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'allow_compare_match' => false]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="compareMatchOpen"', false);

        $owner->update(['allow_compare_match' => true]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="compareMatchOpen"', false);
    }

    public function test_compare_match_endpoint_is_forbidden_when_permission_is_off(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'allow_compare_match' => false]);

        $this->actingAs($owner)
            ->postJson(route('dashboard.compare-match.run'), ['mode' => 'empty'])
            ->assertForbidden();
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
