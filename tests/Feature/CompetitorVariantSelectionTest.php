<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetitorVariantSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_t_mobile_woocommerce_variants_are_extracted(): void
    {
        $url = 'https://2tmobile.com/macbook-air-15-inch-2024-m3-like-new/';

        Http::fake([
            $url => Http::response($this->twoTMobileHtml(), 200),
        ]);

        $variants = (new PriceScraper)->scrapeKnownSiteVariants($url);

        $this->assertCount(2, $variants);
        $this->assertSame('46838', $variants[0]['key']);
        $this->assertSame('Cấu hình 1', $variants[0]['name']);
        $this->assertSame(20500000, $variants[0]['price']);
        $this->assertSame('46839', $variants[1]['key']);
        $this->assertSame('Cấu hình 2', $variants[1]['name']);
        $this->assertSame(23000000, $variants[1]['price']);
    }

    public function test_adjustment_popup_lists_variants_for_configured_competitor_url(): void
    {
        [$user, $competitor, $url] = $this->createTwoTMobileCompetitor();

        Http::fake([
            $url => Http::response($this->twoTMobileHtml(), 200),
        ]);

        $this->actingAs($user)
            ->getJson(route('competitors.variants', $competitor))
            ->assertOk()
            ->assertJsonFragment([
                'key' => '46838',
                'name' => 'Cấu hình 1',
                'price' => 20500000,
                'price_text' => '20.500.000đ',
            ])
            ->assertJsonFragment([
                'key' => '46839',
                'name' => 'Cấu hình 2',
                'price' => 23000000,
                'price_text' => '23.000.000đ',
            ]);
    }

    public function test_user_can_select_variant_on_price_adjustment_and_variant_price_is_saved(): void
    {
        [$user, $competitor, $url] = $this->createTwoTMobileCompetitor();

        Http::fake([
            $url => Http::response($this->twoTMobileHtml(), 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('competitors.adjustment.update', $competitor), [
                'price_adjustment' => '0',
                'variant_key' => '46839',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'ok' => true,
                'variant_key' => '46839',
                'variant_name' => 'Cấu hình 2',
                'variant_price' => 23000000,
                'reload' => true,
            ]);

        $competitor->refresh();

        $this->assertSame('46839', $competitor->variant_key);
        $this->assertSame('Cấu hình 2', $competitor->variant_name);
        $this->assertDatabaseHas('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 23000000,
        ]);
    }

    public function test_saving_same_url_keeps_selected_variant_and_changing_url_clears_it(): void
    {
        [$user, $competitor, $url] = $this->createTwoTMobileCompetitor([
            'variant_key' => '46839',
            'variant_name' => 'Cấu hình 2',
        ]);

        Http::fake([
            $url => Http::response($this->twoTMobileHtml(), 200),
            'https://2tmobile.com/another-product/' => Http::response($this->twoTMobileHtml(), 200),
        ]);

        $this->actingAs($user)
            ->put(route('competitors.url.update', $competitor), [
                'url' => $url,
            ])
            ->assertRedirect();

        $competitor->refresh();
        $this->assertSame('46839', $competitor->variant_key);
        $this->assertSame('Cấu hình 2', $competitor->variant_name);

        $this->actingAs($user)
            ->put(route('competitors.url.update', $competitor), [
                'url' => 'https://2tmobile.com/another-product/',
            ])
            ->assertRedirect();

        $competitor->refresh();
        $this->assertNull($competitor->variant_key);
        $this->assertNull($competitor->variant_name);
    }

    /**
     * @param  array<string, mixed>  $competitorAttributes
     * @return array{0: User, 1: Competitor, 2: string}
     */
    private function createTwoTMobileCompetitor(array $competitorAttributes = []): array
    {
        $user = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $user->id,
            'name' => 'MacBook của tôi',
            'price' => 21000000,
            'product_url' => 'https://my-shop.test/macbook',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $user->id,
            'name' => '2tmobile.com',
            'domain' => '2tmobile.com',
        ]);
        $url = 'https://2tmobile.com/macbook-air-15-inch-2024-m3-like-new/';
        $competitor = Competitor::query()->create(array_merge([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => '2tmobile.com',
            'url' => $url,
        ], $competitorAttributes));

        return [$user, $competitor, $url];
    }

    private function twoTMobileHtml(): string
    {
        $variations = [
            [
                'variation_id' => 46838,
                'attributes' => ['attribute_pa_cau-hinh' => 'cau-hinh-1'],
                'display_price' => 20500000,
                'display_regular_price' => 21500000,
                'variation_is_visible' => true,
                'variation_is_active' => true,
            ],
            [
                'variation_id' => 46839,
                'attributes' => ['attribute_pa_cau-hinh' => 'cau-hinh-2'],
                'display_price' => 23000000,
                'display_regular_price' => 24000000,
                'variation_is_visible' => true,
                'variation_is_active' => true,
            ],
            [
                'variation_id' => 46840,
                'attributes' => ['attribute_pa_cau-hinh' => 'cau-hinh-het-hang'],
                'display_price' => 25000000,
                'variation_is_visible' => true,
                'variation_is_active' => false,
            ],
        ];
        $encoded = htmlspecialchars(json_encode($variations, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<html>
<head><meta property="og:title" content="MacBook Air 15 inch 2024 M3 Like New" /></head>
<body>
    <h1>MacBook Air 15 inch 2024 M3 Like New</h1>
    <form class="variations_form cart" data-product_variations="{$encoded}">
        <select id="pa_cau-hinh" name="attribute_pa_cau-hinh">
            <option value="">Chọn một tùy chọn</option>
            <option value="cau-hinh-1">Cấu hình 1</option>
            <option value="cau-hinh-2">Cấu hình 2</option>
            <option value="cau-hinh-het-hang">Cấu hình hết hàng</option>
        </select>
    </form>
</body>
</html>
HTML;
    }
}
