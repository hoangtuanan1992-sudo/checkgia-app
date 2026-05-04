<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
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
            ->assertSee('id="compareCardColumnsWrap"', false)
            ->assertSee('id="compareCardColumns"', false)
            ->assertSee('value="50" selected', false)
            ->assertSee('Bạn muốn xem trang:', false)
            ->assertSee('id="comparePageButtons"', false)
            ->assertSee('id="comparisonFloatingPager"', false)
            ->assertSee('id="compareFloatingPrev"', false)
            ->assertSee('id="compareFloatingNext"', false)
            ->assertSee('id="bulkDeleteOpen"', false)
            ->assertSee('id="bulkDeleteDialog"', false)
            ->assertDontSee('id="filterReset"', false)
            ->assertSee('class="compare-sticky-name"', false)
            ->assertSee('class="compare-sticky-price"', false)
            ->assertSee('checkgia_compare_per_page', false)
            ->assertSee('checkgia_compare_card_columns', false);
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

    public function test_dashboard_comparison_table_can_sort_products_by_abc(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Charlie Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/charlie',
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Alpha Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/alpha',
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Beta Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/beta',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard', ['sort' => 'name_asc']))
            ->assertOk()
            ->assertSee('value="name_asc" selected', false)
            ->assertSeeInOrder([
                'Alpha Product',
                'Beta Product',
                'Charlie Product',
            ]);
    }

    public function test_dashboard_bulk_delete_removes_only_filtered_products(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $groupA = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Group A',
        ]);
        $groupB = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Group B',
        ]);

        $deleteA = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $groupA->id,
            'name' => 'Phone Alpha',
            'price' => 1000000,
            'product_url' => 'https://shop.test/phone-alpha',
        ]);
        $deleteB = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $groupA->id,
            'name' => 'Phone Beta',
            'price' => 1000000,
            'product_url' => 'https://shop.test/phone-beta',
        ]);
        $keepSameGroup = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $groupA->id,
            'name' => 'Laptop Alpha',
            'price' => 1000000,
            'product_url' => 'https://shop.test/laptop-alpha',
        ]);
        $keepSameSearch = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $groupB->id,
            'name' => 'Phone Gamma',
            'price' => 1000000,
            'product_url' => 'https://shop.test/phone-gamma',
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('dashboard.products.bulk-destroy', [
                'q' => 'Phone',
                'group' => $groupA->id,
            ]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'matched' => 2,
                'deleted' => 2,
            ]);

        $this->assertDatabaseMissing('products', ['id' => $deleteA->id]);
        $this->assertDatabaseMissing('products', ['id' => $deleteB->id]);
        $this->assertDatabaseHas('products', ['id' => $keepSameGroup->id]);
        $this->assertDatabaseHas('products', ['id' => $keepSameSearch->id]);
    }
}
