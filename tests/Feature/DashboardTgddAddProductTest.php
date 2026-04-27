<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProductPrices;
use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTgddAddProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_store_uses_tgdd_api_first_and_saves_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserScrapeSetting::create([
            'user_id' => $user->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'price_regex' => null,
        ]);

        $url = 'https://www.thegioididong.com/dtdd/iphone-17e-512gb?utm_flashsale=1';

        $pageHtml = '<html><body><img src="/Products/Images/42/123456/abc.jpg"></body></html>';
        $snippet = '<div class="viewed-product" data-id="123456"><a data-price="34900000" data-name="iPhone 17e 512GB"></a><span class="viewed-product-price">34.900.000₫</span><div class="viewed-product-title">iPhone 17e 512GB</div></div>';

        Http::fake([
            $url => Http::response($pageHtml, 200),
            'https://www.thegioididong.com/Ajax/GetViewedHistory' => Http::response($snippet, 200),
        ]);

        $this->post('/dashboard/products', [
            'product_url' => $url,
        ])->assertRedirect('/dashboard');

        $product = Product::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($product);
        $this->assertSame(34900000, (int) $product->price);
        $this->assertSame('iPhone 17e 512GB', (string) $product->name);

        Http::assertSentCount(2);
    }

    public function test_dashboard_store_updates_existing_product_instead_of_creating_duplicate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserScrapeSetting::create([
            'user_id' => $user->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'price_regex' => null,
        ]);

        $url = 'https://example.com/p1';

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'SP cũ',
            'price' => 0,
            'product_url' => $url,
        ]);

        $site = CompetitorSite::create([
            'user_id' => $user->id,
            'name' => 'doithu.com',
            'domain' => 'doithu.com',
            'position' => 1,
        ]);

        Competitor::create([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => $site->name,
            'url' => 'https://doithu.com/old',
        ]);

        Bus::fake();

        $this->post('/dashboard/products', [
            'product_url' => $url,
            'competitor_urls' => [
                'a' => 'https://doithu.com/new',
                'b' => 'https://doithu2.com/p',
            ],
        ])->assertRedirect('/dashboard');

        $this->assertSame(1, Product::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Competitor::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('competitors', [
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'url' => 'https://doithu.com/new',
        ]);

        Bus::assertDispatched(ScrapeProductPrices::class);
    }
}
