<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subuser_report_only_uses_allowed_product_and_competitor_groups(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $allowedProductGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Allowed group',
        ]);
        $blockedProductGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Blocked group',
        ]);

        $allowedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'allowed-site.test',
            'position' => 1,
        ]);
        $blockedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'blocked-site.test',
            'position' => 2,
        ]);

        $allowedCompetitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Allowed competitors',
        ]);
        $allowedCompetitorGroup->competitorSites()->sync([$allowedSite->id]);

        $allowedProduct = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $allowedProductGroup->id,
            'name' => 'Allowed report product',
            'price' => 1000000,
            'product_url' => 'https://own.test/allowed',
        ]);
        $blockedProduct = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $blockedProductGroup->id,
            'name' => 'Blocked report product',
            'price' => 1000000,
            'product_url' => 'https://own.test/blocked',
        ]);

        $allowedCompetitor = Competitor::query()->create([
            'product_id' => $allowedProduct->id,
            'competitor_site_id' => $allowedSite->id,
            'name' => $allowedSite->name,
            'url' => 'https://allowed-site.test/product',
        ]);
        $blockedSiteCompetitor = Competitor::query()->create([
            'product_id' => $allowedProduct->id,
            'competitor_site_id' => $blockedSite->id,
            'name' => $blockedSite->name,
            'url' => 'https://blocked-site.test/allowed-product',
        ]);
        $blockedProductCompetitor = Competitor::query()->create([
            'product_id' => $blockedProduct->id,
            'competitor_site_id' => $allowedSite->id,
            'name' => $allowedSite->name,
            'url' => 'https://allowed-site.test/blocked-product',
        ]);

        ProductPriceHistory::query()->create([
            'product_id' => $allowedProduct->id,
            'price' => 1000000,
            'fetched_at' => now(),
        ]);
        ProductPriceHistory::query()->create([
            'product_id' => $blockedProduct->id,
            'price' => 1000000,
            'fetched_at' => now(),
        ]);

        CompetitorPrice::query()->create([
            'competitor_id' => $allowedCompetitor->id,
            'price' => 900000,
            'fetched_at' => now(),
        ]);
        CompetitorPrice::query()->create([
            'competitor_id' => $blockedSiteCompetitor->id,
            'price' => 100000,
            'fetched_at' => now(),
        ]);
        CompetitorPrice::query()->create([
            'competitor_id' => $blockedProductCompetitor->id,
            'price' => 800000,
            'fetched_at' => now(),
        ]);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedProductGroup->id],
            'visible_competitor_site_group_ids' => [$allowedCompetitorGroup->id],
        ]);

        $this->actingAs($subUser)
            ->get(route('dashboard.reports'))
            ->assertOk()
            ->assertSee('Allowed report product')
            ->assertSee('allowed-site.test')
            ->assertSee('-100.000')
            ->assertDontSee('Blocked report product')
            ->assertDontSee('blocked-site.test')
            ->assertDontSee('-900.000');
    }
}
