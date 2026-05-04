<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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
}
