<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubUserGroupVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_update_subuser_group_visibility(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $productGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tủ lạnh',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Điện máy đỏ',
            'position' => 1,
        ]);
        $competitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Điện máy',
        ]);
        $competitorGroup->competitorSites()->sync([$site->id]);

        $this->actingAs($owner)
            ->post(route('account.subusers.store'), [
                'name' => 'Tài khoản con',
                'email' => 'viewer@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'product_group_ids' => [$productGroup->id],
                'competitor_site_group_ids' => [$competitorGroup->id],
            ])
            ->assertRedirect();

        $subUser = User::query()->where('email', 'viewer@example.com')->firstOrFail();
        $this->assertSame([$productGroup->id], $subUser->visibleProductGroupIds());
        $this->assertSame([$competitorGroup->id], $subUser->visibleCompetitorSiteGroupIds());

        $this->actingAs($owner)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('viewer@example.com')
            ->assertSee('Tủ lạnh')
            ->assertSee('Điện máy');

        $this->actingAs($owner)
            ->put(route('account.subusers.update', $subUser), [
                'name' => 'Tài khoản đã sửa',
                'email' => 'viewer-updated@example.com',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect();

        $subUser->refresh();
        $this->assertSame('Tài khoản đã sửa', $subUser->name);
        $this->assertSame('viewer-updated@example.com', $subUser->email);
        $this->assertTrue(Hash::check('newpassword123', $subUser->password));
        $this->assertSame([], $subUser->visibleProductGroupIds());
        $this->assertSame([], $subUser->visibleCompetitorSiteGroupIds());
    }

    public function test_subuser_dashboard_only_shows_allowed_products_and_competitor_sites(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $allowedProductGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tủ lạnh',
        ]);
        $blockedProductGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tivi',
        ]);
        $allowedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Đối thủ được xem',
            'position' => 1,
        ]);
        $blockedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Đối thủ bị ẩn',
            'position' => 2,
        ]);
        $allowedCompetitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Nhóm được xem',
        ]);
        $allowedCompetitorGroup->competitorSites()->sync([$allowedSite->id]);
        $blockedCompetitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Nhóm bị ẩn',
        ]);
        $blockedCompetitorGroup->competitorSites()->sync([$blockedSite->id]);

        $allowedProduct = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $allowedProductGroup->id,
            'name' => 'Tủ lạnh được xem',
            'price' => 12000000,
            'product_url' => 'https://shop.test/tu-lanh',
        ]);
        Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $blockedProductGroup->id,
            'name' => 'Tivi bị ẩn',
            'price' => 9000000,
            'product_url' => 'https://shop.test/tivi',
        ]);
        Competitor::query()->create([
            'product_id' => $allowedProduct->id,
            'competitor_site_id' => $allowedSite->id,
            'name' => $allowedSite->name,
            'url' => 'https://allowed.test/tu-lanh',
        ]);
        Competitor::query()->create([
            'product_id' => $allowedProduct->id,
            'competitor_site_id' => $blockedSite->id,
            'name' => $blockedSite->name,
            'url' => 'https://blocked.test/tu-lanh',
        ]);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedProductGroup->id],
            'visible_competitor_site_group_ids' => [$allowedCompetitorGroup->id],
        ]);

        $this->actingAs($subUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tủ lạnh được xem')
            ->assertSee('Đối thủ được xem')
            ->assertDontSee('Tivi bị ẩn')
            ->assertDontSee('Đối thủ bị ẩn')
            ->assertDontSee('https://blocked.test/tu-lanh');
    }

    public function test_subuser_without_allowed_groups_sees_no_comparison_products_or_competitor_sites(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $productGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Allowed Later',
        ]);
        $competitorSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'Hidden Competitor',
            'position' => 1,
        ]);
        $competitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Hidden Competitor Group',
        ]);
        $competitorGroup->competitorSites()->sync([$competitorSite->id]);

        $product = Product::query()->create([
            'user_id' => $owner->id,
            'product_group_id' => $productGroup->id,
            'name' => 'Hidden Product',
            'price' => 1000000,
            'product_url' => 'https://shop.test/hidden-product',
        ]);
        Competitor::query()->create([
            'product_id' => $product->id,
            'competitor_site_id' => $competitorSite->id,
            'name' => $competitorSite->name,
            'url' => 'https://competitor.test/hidden-product',
        ]);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [],
            'visible_competitor_site_group_ids' => [],
        ]);

        $this->actingAs($subUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Hidden Product')
            ->assertDontSee('Hidden Competitor')
            ->assertDontSee('https://competitor.test/hidden-product')
            ->assertSee('Chưa được cấp nhóm');
    }
}
