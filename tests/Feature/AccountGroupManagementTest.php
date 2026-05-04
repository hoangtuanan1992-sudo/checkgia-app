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
            ->post(route('account.product-groups.delete-post', $group))
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
            ->put(route('account.competitor-site-groups.update', $group), [
                'name' => 'Di động',
                'competitor_site_ids' => [$siteB->id],
            ])
            ->assertRedirect();

        $group->refresh();
        $this->assertSame('Di động', $group->name);
        $this->assertSame([$siteB->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($owner)
            ->delete(route('account.competitor-site-groups.destroy', $group))
            ->assertRedirect();

        $this->assertDatabaseMissing('competitor_site_groups', [
            'id' => $group->id,
        ]);
    }
}
