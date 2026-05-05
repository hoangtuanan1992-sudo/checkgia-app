<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardQuickScanFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_scan_can_filter_new_products_from_latest_scan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));

        $owner = User::factory()->create(['role' => 'owner']);
        $previousJobId = $this->insertJob('previous-job', Carbon::now()->subDays(2));
        $latestJobId = $this->insertJob('latest-job', Carbon::now());

        $this->insertProduct($previousJobId, 'Old Alpha', 'https://example.com/old-alpha', 1000000, Carbon::now()->subDays(2));
        $this->insertProduct($latestJobId, 'Old Alpha', 'https://example.com/old-alpha', 1000000, Carbon::now());
        $this->insertProduct($latestJobId, 'New Beta', 'https://example.com/new-beta', 2000000, Carbon::now());

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', [
                'website_url' => 'https://example.com/',
                'product_filter' => 'newest',
            ]))
            ->assertOk()
            ->assertSee('Sản phẩm mới nhất')
            ->assertSee('New Beta')
            ->assertDontSee('Old Alpha');
    }

    public function test_quick_scan_can_filter_products_with_and_without_price(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $jobId = $this->insertJob('price-filter-job', now());

        $this->insertProduct($jobId, 'Priced Product', 'https://example.com/priced', 1000000, now());
        $this->insertProduct($jobId, 'No Price Product', 'https://example.com/no-price', null, now());

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', [
                'website_url' => 'https://example.com/',
                'product_filter' => 'priced',
            ]))
            ->assertOk()
            ->assertSee('Priced Product')
            ->assertDontSee('No Price Product');

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', [
                'website_url' => 'https://example.com/',
                'product_filter' => 'unpriced',
            ]))
            ->assertOk()
            ->assertSee('No Price Product')
            ->assertDontSee('Priced Product');
    }

    public function test_quick_scan_marks_products_already_added_to_comparison_without_checking_them(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $jobId = $this->insertJob('already-added-job', now());
        $scannerProductId = $this->insertProduct($jobId, 'Already Added Product', 'https://example.com/already-added', 1000000, now());

        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Already Added Product',
            'price' => 1000000,
            'product_url' => 'https://example.com/already-added',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', [
                'website_url' => 'https://example.com/',
            ]));

        $response->assertOk()
            ->assertSee('data-quick-scan-in-compare-row="1"', false)
            ->assertSee('data-quick-scan-in-compare="1"', false);
        $this->assertMatchesRegularExpression(
            '/data-quick-scan-in-compare="1"\s+type="checkbox"\s+value="'.$scannerProductId.'"\s+style=/',
            $response->getContent()
        );
    }

    private function insertJob(string $externalJobId, Carbon $pushedAt): int
    {
        return (int) DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => $externalJobId,
            'app' => 'windows-product-scanner',
            'start_url' => 'https://example.com/',
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'last_pushed_at' => $pushedAt,
            'created_at' => $pushedAt,
            'updated_at' => $pushedAt,
        ]);
    }

    private function insertProduct(int $jobId, string $name, string $url, ?int $price, Carbon $at): int
    {
        $sourceUrl = 'https://example.com/';
        return (int) DB::table('scanner_import_products')->insertGetId([
            'scanner_import_job_id' => $jobId,
            'external_id' => sha1($url),
            'external_job_id' => 'job-'.$jobId,
            'product_code' => null,
            'name' => $name,
            'price_text' => $price ? number_format($price, 0, ',', '.').'đ' : null,
            'price_value' => $price,
            'currency' => 'VND',
            'url' => $url,
            'link' => $url,
            'source_url' => $sourceUrl,
            'url_hash' => sha1(mb_strtolower($url)),
            'source_url_hash' => sha1(mb_strtolower($sourceUrl)),
            'dedupe_hash' => sha1(mb_strtolower($sourceUrl).'|'.mb_strtolower($url)),
            'imported_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
