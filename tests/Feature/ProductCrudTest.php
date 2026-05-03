<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserScrapeSetting::create([
            'user_id' => $user->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'price_regex' => null,
        ]);

        Http::fake([
            'https://example.com/iphone-15' => Http::response('<html><body><h1>iPhone 15</h1><div id="price">34.900.000 đ</div></body></html>', 200),
        ]);

        $this->post('/products', [
            'product_url' => 'https://example.com/iphone-15',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertSee('iPhone 15');
    }

    public function test_dashboard_can_add_topzone_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.topzone.vn/iphone/iphone-17-pro-max' => Http::response(
                '<html><body><h1>iPhone 17 Pro Max 256GB</h1><strong class="price box_normal" data-price="37990000.0" data-disprice="37990000.0">37.990.000&#x20AB;</strong></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => 'https://www.topzone.vn/iphone/iphone-17-pro-max',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'iPhone 17 Pro Max 256GB',
            'price' => 37990000,
            'product_url' => 'https://www.topzone.vn/iphone/iphone-17-pro-max',
        ]);
    }
}
