<?php

namespace Tests\Feature;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_and_delete_product_groups_from_account(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $group = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Laptop',
        ]);

        $this->actingAs($owner)
            ->put(route('account.product-groups.update', $group), [
                'name' => 'Laptop Gaming',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_groups', [
            'id' => $group->id,
            'name' => 'Laptop Gaming',
        ]);

        $this->actingAs($owner)
            ->get(route('account.product-groups.show', $group))
            ->assertRedirect(route('account'));

        $this->actingAs($owner)
            ->post(route('account.product-groups.update-post', $group), [
                'name' => 'Laptop văn phòng',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_groups', [
            'id' => $group->id,
            'name' => 'Laptop văn phòng',
        ]);

        $this->actingAs($owner)
            ->get(route('account.product-groups.update-post', $group))
            ->assertRedirect(route('account'));

        $this->actingAs($owner)
            ->get(route('account.product-groups.update-post', ['productGroup' => $group, 'name' => 'Laptop đồ họa']))
            ->assertRedirect();

        $this->assertDatabaseHas('product_groups', [
            'id' => $group->id,
            'name' => 'Laptop đồ họa',
        ]);

        $this->actingAs($owner)
            ->get(route('account', [
                'product_group_action' => 'update',
                'product_group_id' => $group->id,
                'product_group_name' => 'Laptop route cũ',
            ]))
            ->assertRedirect(route('account'));

        $this->assertDatabaseHas('product_groups', [
            'id' => $group->id,
            'name' => 'Laptop route cũ',
        ]);

        $this->actingAs($owner)
            ->get(route('account', [
                'product_group_action' => 'delete',
                'product_group_id' => $group->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_owner_can_manage_competitor_site_groups_from_account(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $siteA = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'topzone.vn',
            'position' => 1,
        ]);
        $siteB = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'viettelstore.vn',
            'position' => 2,
        ]);

        $this->actingAs($owner)
            ->post(route('account.competitor-site-groups.store'), [
                'name' => 'Điện thoại',
                'competitor_site_ids' => [$siteA->id],
            ])
            ->assertRedirect();

        $group = CompetitorSiteGroup::query()->where('name', 'Điện thoại')->firstOrFail();
        $this->assertSame([$siteA->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($owner)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('name="competitor_group_action" value="update"', false)
            ->assertSee('name="competitor_group_action" value="delete"', false)
            ->assertDontSee('/account/competitor-site-groups/'.$group->id.'/update', false)
            ->assertDontSee('/account/competitor-site-groups/'.$group->id.'/delete', false);

        $this->actingAs($owner)
            ->put(route('account.competitor-site-groups.update', $group), [
                'name' => 'Di động',
                'competitor_site_ids' => [$siteB->id],
            ])
            ->assertRedirect();

        $group->refresh();
        $this->assertSame('Di động', $group->name);
        $this->assertSame([$siteB->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($owner)
            ->get(route('account.competitor-site-groups.show', $group))
            ->assertRedirect(route('account'));

        $this->actingAs($owner)
            ->post(route('account.competitor-site-groups.update-post', $group), [
                'name' => 'Di dong POST',
                'competitor_site_ids' => [$siteA->id, $siteB->id],
            ])
            ->assertRedirect();

        $group->refresh();
        $this->assertSame('Di dong POST', $group->name);
        $syncedSiteIds = $group->competitorSites()->pluck('competitor_sites.id')->sort()->values()->all();
        $this->assertSame([$siteA->id, $siteB->id], $syncedSiteIds);

        $this->actingAs($owner)
            ->get(route('account.competitor-site-groups.update-post', [
                'competitorSiteGroup' => $group,
                'name' => 'Di dong GET fallback',
                'competitor_site_ids' => [$siteA->id],
            ]))
            ->assertRedirect();

        $group->refresh();
        $this->assertSame('Di dong GET fallback', $group->name);
        $this->assertSame([$siteA->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($owner)
            ->get(route('account', [
                'competitor_group_action' => 'update',
                'competitor_group_id' => $group->id,
                'competitor_group_name' => 'Di dong query',
                'competitor_site_ids' => [$siteB->id],
            ]))
            ->assertRedirect(route('account'));

        $group->refresh();
        $this->assertSame('Di dong query', $group->name);
        $this->assertSame([$siteB->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($owner)
            ->post(route('account.competitor-site-groups.delete-post', $group))
            ->assertRedirect();

        $this->assertDatabaseMissing('competitor_site_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_owner_can_update_subuser_groups_through_account_query_route(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'name' => 'Viewer cũ',
            'email' => 'viewer-old@example.com',
        ]);
        $productGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Tủ lạnh',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'dienmaydo.vn',
            'position' => 1,
        ]);
        $competitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Điện máy',
        ]);
        $competitorGroup->competitorSites()->sync([$site->id]);

        $this->actingAs($owner)
            ->get(route('account.subusers.show', $subUser))
            ->assertRedirect(route('account'));

        $this->actingAs($owner)
            ->get(route('account', [
                'subuser_action' => 'update',
                'subuser_id' => $subUser->id,
                'name' => 'Viewer mới',
                'email' => 'viewer-new@example.com',
                'product_group_ids' => [$productGroup->id],
                'competitor_site_group_ids' => [$competitorGroup->id],
            ]))
            ->assertRedirect(route('account'));

        $subUser->refresh();
        $this->assertSame('Viewer mới', $subUser->name);
        $this->assertSame('viewer-new@example.com', $subUser->email);
        $this->assertSame([$productGroup->id], $subUser->visibleProductGroupIds());
        $this->assertSame([$competitorGroup->id], $subUser->visibleCompetitorSiteGroupIds());
    }
}
