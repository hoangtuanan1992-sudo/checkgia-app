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

    public function test_quick_scan_remembers_last_website_when_opened_without_query(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $rememberedJobId = $this->insertJob('remembered-job', now());
        $this->insertProduct($rememberedJobId, 'Remembered Product', 'https://example.com/remembered', 1000000, now());

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', ['website_url' => 'https://example.com/']))
            ->assertOk()
            ->assertSee('Remembered Product');

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan'))
            ->assertOk()
            ->assertSee('value="https://example.com"', false)
            ->assertSee('Remembered Product');

        $otherJobId = $this->insertJob('other-job', now(), 'https://other.test/');
        $this->insertProduct($otherJobId, 'Other Product', 'https://other.test/product', 2000000, now(), 'https://other.test/');

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan', ['website_url' => 'https://other.test/']))
            ->assertOk()
            ->assertSee('Other Product')
            ->assertDontSee('Remembered Product');

        $this->actingAs($owner)
            ->get(route('dashboard.quick-scan'))
            ->assertOk()
            ->assertSee('value="https://other.test"', false)
            ->assertSee('Other Product')
            ->assertDontSee('Remembered Product');
    }

    public function test_subuser_quick_scan_only_lists_allowed_product_groups(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $now = now();
        $allowedGroupId = DB::table('product_groups')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Allowed Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('product_groups')->insert([
            'user_id' => $owner->id,
            'name' => 'Blocked Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $jobId = $this->insertJob('viewer-group-list-job', $now);
        $this->insertProduct($jobId, 'Viewer Product', 'https://example.com/viewer-product', 1000000, $now);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedGroupId],
        ]);

        $this->actingAs($subUser)
            ->get(route('dashboard.quick-scan', ['website_url' => 'https://example.com/']))
            ->assertOk()
            ->assertSee('Allowed Group')
            ->assertDontSee('Blocked Group')
            ->assertDontSee('-- Không chọn nhóm --');
    }

    public function test_legacy_subuser_quick_scan_only_lists_allowed_product_groups(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $now = now();
        $allowedGroupId = DB::table('product_groups')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Allowed Legacy Quick Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('product_groups')->insert([
            'user_id' => $owner->id,
            'name' => 'Blocked Legacy Quick Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $jobId = $this->insertJob('legacy-viewer-group-list-job', $now);
        $this->insertProduct($jobId, 'Legacy Viewer Product', 'https://example.com/legacy-viewer-product', 1000000, $now);

        $subUser = User::factory()->create([
            'role' => 'owner',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedGroupId],
        ]);

        $this->actingAs($subUser)
            ->get(route('dashboard.quick-scan', ['website_url' => 'https://example.com/']))
            ->assertOk()
            ->assertSee('Allowed Legacy Quick Group')
            ->assertDontSee('Blocked Legacy Quick Group')
            ->assertDontSee('-- Không chọn nhóm --');
    }

    public function test_subuser_must_add_scanned_products_to_an_allowed_product_group(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $now = now();
        $allowedGroupId = DB::table('product_groups')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Allowed Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $blockedGroupId = DB::table('product_groups')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Blocked Group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $jobId = $this->insertJob('viewer-add-job', $now);
        $url = 'https://example.com/viewer-add-product';
        $scannerProductId = $this->insertProduct($jobId, 'Viewer Add Product', $url, 1000000, $now);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedGroupId],
        ]);

        $this->actingAs($subUser)
            ->post(route('dashboard.quick-scan.add-to-compare'), [
                'website_url' => 'https://example.com/',
                'scanner_product_ids_json' => json_encode([$scannerProductId]),
            ])
            ->assertRedirect();
        $this->assertDatabaseMissing('products', [
            'user_id' => $owner->id,
            'product_url' => $url,
        ]);

        $this->actingAs($subUser)
            ->post(route('dashboard.quick-scan.add-to-compare'), [
                'website_url' => 'https://example.com/',
                'product_group_id' => $blockedGroupId,
                'scanner_product_ids_json' => json_encode([$scannerProductId]),
            ])
            ->assertRedirect();
        $this->assertDatabaseMissing('products', [
            'user_id' => $owner->id,
            'product_url' => $url,
        ]);

        $this->actingAs($subUser)
            ->post(route('dashboard.quick-scan.add-to-compare'), [
                'website_url' => 'https://example.com/',
                'product_group_id' => $allowedGroupId,
                'scanner_product_ids_json' => json_encode([$scannerProductId]),
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('products', [
            'user_id' => $owner->id,
            'product_group_id' => $allowedGroupId,
            'name' => 'Viewer Add Product',
            'price' => 1000000,
            'product_url' => $url,
        ]);
    }

    private function insertJob(string $externalJobId, Carbon $pushedAt, string $startUrl = 'https://example.com/'): int
    {
        return (int) DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => $externalJobId,
            'app' => 'windows-product-scanner',
            'start_url' => $startUrl,
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'last_pushed_at' => $pushedAt,
            'created_at' => $pushedAt,
            'updated_at' => $pushedAt,
        ]);
    }

    private function insertProduct(int $jobId, string $name, string $url, ?int $price, Carbon $at, string $sourceUrl = 'https://example.com/'): int
    {
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
