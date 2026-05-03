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

    public function test_dashboard_can_add_viettelstore_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://viettelstore.vn/dien-thoai/samsung-galaxy-s26-plus-pid362293.html';

        Http::fake([
            $url => Http::response(
                '<html><head><meta property="og:title" content="Samsung Galaxy S26 Plus 12GB | 256GB chinh hang - ViettelStore.vn" /></head><body><script type="application/ld+json">{"@context":"http://schema.org/","@type":"Product","name":"Samsung Galaxy S26 Plus 12GB 256GB","offers":{"@type":"AggregateOffer","Price":"24290000","priceCurrency":"VND"}}</script></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Samsung Galaxy S26 Plus 12GB 256GB',
            'price' => 24290000,
            'product_url' => $url,
        ]);
    }

    public function test_dashboard_can_add_mi_com_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://www.mi.com/vn/product/poco-pad-x1/';

        Http::fake([
            $url => Http::response(
                '<html><body><script type="application/ld+json">{"@context":"http://schema.org/","@type":"Product","name":"POCO Pad X1","brand":{"@type":"Brand","name":"Xiaomi"}}</script><div class="xm-price"><p class="xm-price--items"></p></div></body></html>',
                200
            ),
            'https://go.buy.mi.com/vn/v2/item/productinfo*' => Http::response([
                'errno' => 0,
                'errmsg' => '',
                'data' => [
                    'item_min_price' => 10290000,
                    'rrp' => 11290000,
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'POCO Pad X1',
            'price' => 10290000,
            'product_url' => $url,
        ]);
    }

    public function test_dashboard_can_add_hoanghamobile_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://hoanghamobile.com/may-tinh-bang/may-tinh-bang-redmi-pad-se-8-7-4g-6gb-128gb';

        Http::fake([
            $url => Http::response(
                '<html><head><script>window.insider_object = {}; window.insider_object.product = {"id":"5520","name":"May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB","currency":"VND","unit_price":5490000.0,"unit_sale_price":3790000.0,"url":"'.$url.'","stock":212,"is_available":true,"custom":{"sku":[{"sku":"PASE8R6XD","name":"Xanh Duong","price":3790000.0}]}};</script></head><body><div class="product-detail"><h1>May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB</h1></div><div class="box-price"><strong class="price">3.790.000 &#x20AB;</strong></div></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB',
            'price' => 3790000,
            'product_url' => $url,
        ]);
    }

    public function test_dashboard_can_add_minhtuanmobile_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://minhtuanmobile.com/iphone-17-25091002335142/';

        Http::fake([
            $url => Http::response(
                '<html><head><script type="application/ld+json">{"@context":"https://schema.org/","@type":"Product","name":"iPhone 17 256GB - Chinh hang VN - MG6L4ZP A","sku":"MG6L4ZP/A","offers":{"@type":"Offer","priceCurrency":"VND","price":24190000,"priceSpecification":{"@type":"UnitPriceSpecification","price":24990000}}}</script></head><body><h1>iPhone 17 256GB - Chinh hang VN - MG6L4ZP/A</h1><p class="prodetail__price prodetail__price--buynow mb-1"><b class="price">24,190,000&#x0111;</b><s>24,990,000&#x0111;</s></p></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'iPhone 17 256GB - Chinh hang VN - MG6L4ZP/A',
            'price' => 24190000,
            'product_url' => $url,
        ]);
    }

    public function test_dashboard_can_add_minhtuanmobile_short_slug_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://minhtuanmobile.com/iphone-15-128gb/';

        Http::fake([
            $url => Http::response(
                '<html><head><script type="application/ld+json">{"@context":"https://schema.org/","@type":"Product","name":"iPhone 15 128GB - Chinh hang VN A","sku":"MTP13VN/A","offers":{"@type":"Offer","url":"'.$url.'","priceCurrency":"VND","price":17490000,"priceSpecification":{"@type":"UnitPriceSpecification","price":19990000}}}</script></head><body><h1>iPhone 15 128GB - Chinh hang VN/A</h1><div class="prodetail_pricebox_main"><p class="prodetail__price prodetail__price--buynow mb-1"><b class="price">17,490,000&#x0111;</b><s>19,990,000&#x0111;</s></p></div></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'iPhone 15 128GB - Chinh hang VN/A',
            'price' => 17490000,
            'product_url' => $url,
        ]);
    }

    public function test_dashboard_can_add_lg_com_product_without_manual_xpath(): void
    {
        $user = User::factory()->create();
        $url = 'https://www.lg.com/vn/tu-lanh/tu-lanh-instaview/gr-x257bg/';

        Http::fake([
            $url => Http::response(
                '<html><head><title data-id="pdp-title">Tu lanh LG Instaview UV nano 635L mau be GR-X257BG | LG Viet Nam</title></head><body><div class="price-area hidden" data-sku="GR-X257BG.AEEPEVN.EAVH.VN.C" data-msrp="55990000" data-pim-model-name="Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG"></div></body></html>',
                200
            ),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.products.store'), [
                'product_url' => $url,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG',
            'price' => 55990000,
            'product_url' => $url,
        ]);
    }
}
