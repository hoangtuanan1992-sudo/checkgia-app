<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProductPrices;
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
}
