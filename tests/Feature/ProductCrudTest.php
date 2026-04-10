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
}
