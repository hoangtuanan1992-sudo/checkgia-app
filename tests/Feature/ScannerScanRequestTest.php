<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScannerScanRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_scan_for_missing_website(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/dashboard/quick-scan/request', [
                'website_url' => 'https://example.com/',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/dashboard/quick-scan', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('scanner_scan_requests', [
            'requested_by_user_id' => $user->id,
            'requested_url' => 'https://example.com',
            'url_key' => 'example.com',
            'status' => 'pending',
        ]);
    }

    public function test_windows_scanner_can_pull_and_accept_scan_request(): void
    {
        config(['services.checkgia_import.api_key' => 'secret-key']);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/dashboard/quick-scan/request', [
            'website_url' => 'https://example.com/',
        ]);

        $nextResponse = $this->withHeader('Authorization', 'Bearer secret-key')
            ->getJson('/api/scan-requests/next');

        $nextResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.url', 'https://example.com');

        $requestId = (int) $nextResponse->json('request.id');

        $this->withHeader('x-api-key', 'secret-key')
            ->postJson('/api/scan-requests/'.$requestId.'/accepted', [
                'externalJobId' => 'scanner-job-1',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('scanner_scan_requests', [
            'id' => $requestId,
            'status' => 'running',
            'external_job_id' => 'scanner-job-1',
        ]);
    }

    public function test_user_can_add_scanned_products_to_compare_table(): void
    {
        $user = User::factory()->create();
        $now = now();

        $jobId = DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => 'job-add-compare',
            'app' => 'windows-product-scanner',
            'start_url' => 'https://dienmaydo.vn/',
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'last_pushed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $url = 'https://dienmaydo.vn/tu-lanh-toshiba-inverter-596-lit-gr-rs780wi-pgv-22-xk';
        $scannerProductId = DB::table('scanner_import_products')->insertGetId([
            'scanner_import_job_id' => $jobId,
            'external_id' => 'toshiba-1',
            'external_job_id' => 'job-add-compare',
            'product_code' => 'GR-RS780WI-PGV(22)-XK',
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
            'price_text' => '14.500.000 d',
            'price_value' => 14500000,
            'currency' => 'VND',
            'url' => $url,
            'link' => $url,
            'source_url' => 'https://dienmaydo.vn/',
            'url_hash' => sha1(mb_strtolower($url)),
            'source_url_hash' => sha1('https://dienmaydo.vn/'),
            'dedupe_hash' => sha1('https://dienmaydo.vn/|'.mb_strtolower($url)),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($user)
            ->post(route('dashboard.quick-scan.add-to-compare'), [
                'website_url' => 'https://dienmaydo.vn/',
                'scanner_product_ids' => [$scannerProductId],
            ]);

        $response->assertRedirect(route('dashboard').'#comparisonCard');

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
            'price' => 14500000,
            'product_url' => $url,
        ]);

        $productId = (int) DB::table('products')->where('product_url', $url)->value('id');
        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $productId,
            'price' => 14500000,
        ]);
    }
}
