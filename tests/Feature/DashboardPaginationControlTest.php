<?php

namespace Tests\Feature;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\Competitor;
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
            ->assertSee('id="filterGroupPicker"', false)
            ->assertSee('id="filterGroupTrigger"', false)
            ->assertSee('id="filterCompetitorGroup"', false)
            ->assertSee('id="assignGroupDialog"', false)
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

    public function test_dashboard_can_filter_competitor_columns_by_competitor_group(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $siteInGroup = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Site In Group',
            'domain' => 'site-in-group.test',
            'position' => 1,
        ]);
        $siteOutsideGroup = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Site Outside Group',
            'domain' => 'site-outside-group.test',
            'position' => 2,
        ]);
        $group = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Nhom doi thu A',
        ]);
        $group->competitorSites()->attach($siteInGroup->id);

        Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'San pham can loc cot doi thu',
            'price' => 1000000,
            'product_url' => 'https://shop.test/product-filter-competitors',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('dashboard', ['competitor_group' => $group->id]));

        $response->assertOk()
            ->assertSee('id="filterCompetitorGroup"', false)
            ->assertSee('value="'.$group->id.'" selected', false);

        preg_match('/<div id="comparisonResults"[\s\S]*?<div id="comparisonBottomSentinel"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString($siteInGroup->name, $matches[0]);
        $this->assertStringNotContainsString($siteOutsideGroup->name, $matches[0]);
    }

    public function test_owner_can_add_note_to_empty_competitor_link_cell(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'San pham can ghi note',
            'price' => 1000000,
            'product_url' => 'https://shop.test/note-product',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Doi thu can note',
            'position' => 1,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('dashboard.products.competitors.note', [$product, $site]), false)
            ->assertSee('class="compare-note-button js-edit-note"', false);

        $this->actingAs($owner)
            ->post(route('dashboard.products.competitors.note', [$product, $site]), [
                'note' => 'Chua tim duoc link doi thu',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competitors', [
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'url' => '',
            'note' => 'Chua tim duoc link doi thu',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Chua tim duoc link doi thu');
    }

    public function test_clearing_url_keeps_existing_note_visible(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'San pham xoa link giu note',
            'price' => 1000000,
            'product_url' => 'https://shop.test/clear-link-note',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Doi thu co note',
            'position' => 1,
        ]);
        Competitor::query()->create([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => $site->name,
            'url' => 'https://competitor.test/product',
            'note' => 'Giu lai note nay',
        ]);

        $this->actingAs($owner)
            ->post(route('dashboard.products.competitors.upsert', [$product, $site]), [
                'clear' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competitors', [
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'url' => '',
            'note' => 'Giu lai note nay',
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

        $this->assertSoftDeleted('products', ['id' => $deleteA->id]);
        $this->assertSoftDeleted('products', ['id' => $deleteB->id]);
        $this->assertDatabaseHas('products', ['id' => $keepSameGroup->id]);
        $this->assertDatabaseHas('products', ['id' => $keepSameSearch->id]);
    }

    public function test_dashboard_can_assign_filtered_products_to_group(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $targetGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Dien thoai',
        ]);
        $otherGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Laptop',
        ]);

        $assign = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Phone Alpha',
            'price' => 1000000,
            'product_url' => 'https://shop.test/phone-alpha',
        ]);
        $keepNotMatched = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'Laptop Alpha',
            'price' => 1000000,
            'product_url' => 'https://shop.test/laptop-alpha',
        ]);
        $keepAlreadyGrouped = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $otherGroup->id,
            'name' => 'Phone Beta',
            'price' => 1000000,
            'product_url' => 'https://shop.test/phone-beta',
        ]);

        $this->actingAs($owner)
            ->postJson(route('dashboard.products.assign-group', [
                'productGroup' => $targetGroup,
                'q' => 'Phone',
                'group' => '__none__',
            ]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'matched' => 1,
                'updated' => 1,
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $assign->id,
            'product_group_id' => $targetGroup->id,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $keepNotMatched->id,
            'product_group_id' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $keepAlreadyGrouped->id,
            'product_group_id' => $otherGroup->id,
        ]);
    }
}
