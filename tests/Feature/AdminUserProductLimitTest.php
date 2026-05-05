<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserProductLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_existing_product_limit_without_deleting_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create([
            'role' => 'owner',
            'product_limit' => 2000,
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Existing Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/existing',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $owner))
            ->assertOk()
            ->assertSee('name="product_limit"', false)
            ->assertSee('value="2000"', false);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'service_start_date' => null,
                'service_end_date' => null,
                'scrape_schedule_times' => '',
                'auto_delete_failed_products_enabled' => 0,
                'auto_delete_failed_products_days' => 7,
                'admin_note' => 'Keep existing quota',
                'product_limit' => 2000,
                'allow_compare_match' => 0,
                'allow_shopee_check' => 0,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'product_limit' => 2000,
        ]);
        $this->assertDatabaseHas('products', [
            'user_id' => $owner->id,
            'product_url' => 'https://shop.test/existing',
        ]);
    }

    public function test_dashboard_product_store_blocks_new_product_when_limit_is_reached(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'product_limit' => 1,
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Existing Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/existing',
        ]);

        $this->actingAs($owner)
            ->post(route('dashboard.products.store'), [
                'product_url' => 'https://shop.test/new-product',
                'competitor_urls' => [],
            ])
            ->assertSessionHasErrors('product_url');

        $this->assertDatabaseMissing('products', [
            'user_id' => $owner->id,
            'product_url' => 'https://shop.test/new-product',
        ]);
    }

    public function test_quick_scan_add_to_compare_respects_remaining_product_limit(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'product_limit' => 1,
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Existing Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/existing',
        ]);

        $now = now();
        $jobId = DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => 'limit-job',
            'app' => 'windows-product-scanner',
            'start_url' => 'https://scanner.test/',
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'last_pushed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $url = 'https://scanner.test/new-product';
        $scannerProductId = DB::table('scanner_import_products')->insertGetId([
            'scanner_import_job_id' => $jobId,
            'external_id' => 'limit-product',
            'external_job_id' => 'limit-job',
            'product_code' => 'LIMIT-1',
            'name' => 'Limit Product',
            'price_text' => '1.000.000 d',
            'price_value' => 1000000,
            'currency' => 'VND',
            'url' => $url,
            'link' => $url,
            'source_url' => 'https://scanner.test/',
            'url_hash' => sha1(mb_strtolower($url)),
            'source_url_hash' => sha1('https://scanner.test/'),
            'dedupe_hash' => sha1('https://scanner.test/|'.mb_strtolower($url)),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($owner)
            ->post(route('dashboard.quick-scan.add-to-compare'), [
                'website_url' => 'https://scanner.test/',
                'scanner_product_ids_json' => json_encode([$scannerProductId]),
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('products', [
            'user_id' => $owner->id,
            'product_url' => $url,
        ]);
    }
}
