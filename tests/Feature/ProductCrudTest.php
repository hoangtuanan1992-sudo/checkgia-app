<?php

namespace Tests\Feature;

use App\Models\Product;
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

    public function test_dashboard_search_filters_products_across_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Product::create([
            'user_id' => $user->id,
            'name' => 'Máy lạnh Daikin FTKM25AVMV',
            'price' => 10900000,
            'product_url' => 'https://example.com/p1',
        ]);
        Product::create([
            'user_id' => $user->id,
            'name' => 'Tủ lạnh Panasonic',
            'price' => 12000000,
            'product_url' => 'https://example.com/p2',
        ]);

        $this->get('/dashboard?q=Daikin')
            ->assertOk()
            ->assertSee('Máy lạnh Daikin FTKM25AVMV')
            ->assertDontSee('Tủ lạnh Panasonic');
    }
}
