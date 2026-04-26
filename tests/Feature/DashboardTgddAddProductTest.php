<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
