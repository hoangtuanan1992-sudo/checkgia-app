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
}
