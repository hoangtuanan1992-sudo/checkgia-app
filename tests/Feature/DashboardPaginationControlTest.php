<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPaginationControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_comparison_table_has_page_size_controls(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        for ($i = 1; $i <= 55; $i++) {
            Product::query()->create([
                'user_id' => $owner->id,
                'name' => 'San pham '.$i,
                'price' => 1000000 + $i,
                'product_url' => 'https://shop.test/product-'.$i,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="comparisonPagination"', false)
            ->assertSee('id="comparePerPage"', false)
            ->assertSee('value="50" selected', false)
            ->assertSee('Bạn muốn xem trang:', false)
            ->assertSee('id="comparePageButtons"', false)
            ->assertSee('id="comparisonFloatingPager"', false)
            ->assertSee('id="compareFloatingPrev"', false)
            ->assertSee('id="compareFloatingNext"', false)
            ->assertSee('checkgia_compare_per_page', false);
    }

    public function test_dashboard_comparison_table_is_paginated_on_server(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        for ($i = 1; $i <= 55; $i++) {
            Product::query()->create([
                'user_id' => $owner->id,
                'name' => 'San pham '.$i,
                'price' => 1000000 + $i,
                'product_url' => 'https://shop.test/product-'.$i,
            ]);
        }

        $response = $this->actingAs($owner)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('dashboard', ['per_page' => 20, 'page' => 2]));

        $response->assertOk()
            ->assertSee('data-current-page="2"', false)
            ->assertSee('data-page-count="3"', false)
            ->assertSee('data-total="55"', false)
            ->assertSee('data-shown="20"', false);

        $html = $response->getContent();
        preg_match_all('/<tr\b[^>]*data-product-row="/', $html, $rowMatches);
        preg_match_all('/<div\b[^>]*data-product-card="/', $html, $cardMatches);
        $this->assertSame(20, count($rowMatches[0]));
        $this->assertSame(20, count($cardMatches[0]));
    }
}
