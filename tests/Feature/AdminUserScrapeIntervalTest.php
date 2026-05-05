<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProductPrices;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminUserScrapeIntervalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_owner_scrape_schedule_times(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'parent_user_id' => '',
                'service_start_date' => '',
                'service_end_date' => '',
                'scrape_schedule_times' => '20 5 10',
                'admin_note' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user_scrape_settings', [
            'user_id' => $owner->id,
            'scrape_schedule_times' => '5 10 20',
        ]);
    }

    public function test_empty_schedule_uses_ten_minute_interval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'parent_user_id' => '',
                'service_start_date' => '',
                'service_end_date' => '',
                'scrape_schedule_times' => '',
                'admin_note' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user_scrape_settings', [
            'user_id' => $owner->id,
            'scrape_interval_minutes' => 10,
            'scrape_schedule_times' => '',
        ]);
    }

    public function test_admin_can_update_auto_delete_failed_products_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'parent_user_id' => '',
                'service_start_date' => '',
                'service_end_date' => '',
                'scrape_schedule_times' => '',
                'auto_delete_failed_products_enabled' => '1',
                'auto_delete_failed_products_days' => '3',
                'admin_note' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user_scrape_settings', [
            'user_id' => $owner->id,
            'auto_delete_failed_products_enabled' => true,
            'auto_delete_failed_products_days' => 3,
        ]);
    }

    public function test_scrape_job_marks_first_own_product_failure_without_deleting(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'auto_delete_failed_products_enabled' => true,
            'auto_delete_failed_products_days' => 1,
        ]);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Old product',
            'price' => 10000000,
            'product_url' => 'https://example.com/missing-price',
        ]);

        Http::fake([
            'https://example.com/missing-price' => Http::response('<html><body><h1>Old product</h1></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
        $this->assertNotNull($product->fresh()->own_scrape_failed_since);
        Carbon::setTestNow();
    }

    public function test_scrape_job_deletes_product_after_configured_failure_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'auto_delete_failed_products_enabled' => true,
            'auto_delete_failed_products_days' => 2,
        ]);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Old product',
            'price' => 10000000,
            'product_url' => 'https://example.com/still-missing-price',
            'own_scrape_failed_since' => now()->subDays(2)->subMinute(),
        ]);

        Http::fake([
            'https://example.com/still-missing-price' => Http::response('<html><body><h1>Old product</h1></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
        Carbon::setTestNow();
    }

    public function test_scrape_job_treats_contact_price_as_valid_and_resets_failure_counter(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'auto_delete_failed_products_enabled' => true,
            'auto_delete_failed_products_days' => 1,
        ]);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Old product',
            'price' => 10000000,
            'product_url' => 'https://example.com/contact-price',
            'own_scrape_failed_since' => now()->subDays(3),
        ]);

        Http::fake([
            'https://example.com/contact-price' => Http::response('<html><body><h1>Old product contact</h1><div id="price">Lien he</div></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Old product contact',
            'price' => 0,
            'own_scrape_failed_since' => null,
        ]);
        $this->assertDatabaseMissing('product_price_histories', [
            'product_id' => $product->id,
            'price' => 0,
        ]);
        Carbon::setTestNow();
    }

    public function test_scrape_job_uses_approved_xpath_domain_template_without_shop_xpath(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Old name',
            'price' => 1000000,
            'product_url' => 'https://shop.example.com/template-product',
            'own_scrape_failed_since' => now()->subDay(),
        ]);
        CompetitorSiteTemplate::query()->create([
            'domain' => 'example.com',
            'name' => 'Example Shop',
            'name_xpath' => '//h1',
            'price_xpath' => '//*[@id="price"]',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        Http::fake([
            'https://shop.example.com/template-product' => Http::response('<html><body><h1>New template name</h1><div id="price">2.345.000d</div></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New template name',
            'price' => 2345000,
            'own_scrape_failed_since' => null,
        ]);
        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $product->id,
            'price' => 2345000,
        ]);

        Carbon::setTestNow();
    }

    public function test_scrape_job_marks_competitor_price_missing_when_latest_check_has_no_price(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="own-price"]',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Shop test',
            'price_xpath' => '//*[@id="price"]',
        ]);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Product',
            'price' => 10000000,
            'product_url' => 'https://own.test/product',
        ]);
        $competitor = $product->competitors()->create([
            'competitor_site_id' => $site->id,
            'name' => 'Shop test',
            'url' => 'https://shop.test/product',
        ]);
        CompetitorPrice::query()->create([
            'competitor_id' => $competitor->id,
            'price' => 8888000,
            'fetched_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://own.test/product' => Http::response('<html><body><h1>Product</h1><div id="own-price">10.000.000d</div></body></html>', 200),
            'https://shop.test/product' => Http::response('<html><body><div id="price">Lien he</div></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertNotNull($competitor->fresh()->price_missing_at);
        $this->assertDatabaseHas('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 8888000,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('8.888.000', false);

        Carbon::setTestNow();
    }

    public function test_scrape_job_clears_competitor_missing_flag_when_price_returns(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="own-price"]',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Shop test',
            'price_xpath' => '//*[@id="price"]',
        ]);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Product',
            'price' => 10000000,
            'product_url' => 'https://own.test/product',
        ]);
        $competitor = $product->competitors()->create([
            'competitor_site_id' => $site->id,
            'name' => 'Shop test',
            'url' => 'https://shop.test/product',
            'price_missing_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://own.test/product' => Http::response('<html><body><h1>Product</h1><div id="own-price">10.000.000d</div></body></html>', 200),
            'https://shop.test/product' => Http::response('<html><body><div id="price">8.500.000d</div></body></html>', 200),
        ]);

        (new ScrapeProductPrices($product->id))->handle();

        $this->assertDatabaseHas('competitors', [
            'id' => $competitor->id,
            'price_missing_at' => null,
        ]);
        $this->assertDatabaseHas('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 8500000,
        ]);

        Carbon::setTestNow();
    }

    public function test_scrape_due_runs_only_on_configured_schedule_hour(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'scrape_schedule_times' => '5 10 20',
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'iPhone 15',
            'price' => 10000000,
            'product_url' => 'https://example.com/iphone-15',
            'last_scraped_at' => now()->subDay(),
        ]);

        Artisan::call('checkgia:scrape-due');

        Queue::assertPushed(ScrapeProductPrices::class, 1);

        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 11, 0, 0, 'Asia/Ho_Chi_Minh'));

        Artisan::call('checkgia:scrape-due');

        Queue::assertNothingPushed();
        Carbon::setTestNow();
    }

    public function test_scrape_due_runs_every_ten_minutes_when_schedule_is_empty(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 6, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'scrape_interval_minutes' => 5,
            'scrape_schedule_times' => '',
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'iPhone 15',
            'price' => 10000000,
            'product_url' => 'https://example.com/iphone-15',
            'last_scraped_at' => now()->subMinutes(11),
        ]);

        Artisan::call('checkgia:scrape-due');

        Queue::assertPushed(ScrapeProductPrices::class, 1);
        Carbon::setTestNow();
    }

    public function test_scrape_due_dispatches_products_even_without_user_xpath_settings(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 10, 20, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'owner']);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Template only product',
            'price' => 10000000,
            'product_url' => 'https://shop.example.com/product',
            'last_scraped_at' => now()->subMinutes(11),
        ]);

        Artisan::call('checkgia:scrape-due');

        Queue::assertPushed(ScrapeProductPrices::class, 1);
        Carbon::setTestNow();
    }
}
