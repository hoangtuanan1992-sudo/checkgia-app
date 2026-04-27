<?php

namespace Tests\Feature;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorSiteGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_competitor_site_group(): void
    {
        $user = User::factory()->create();

        $site1 = CompetitorSite::create(['user_id' => $user->id, 'name' => 'thegioididong.com', 'position' => 1]);
        $site2 = CompetitorSite::create(['user_id' => $user->id, 'name' => 'fptshop.com.vn', 'position' => 2]);
        $site3 = CompetitorSite::create(['user_id' => $user->id, 'name' => 'cellphones.com.vn', 'position' => 3]);

        $this->actingAs($user)
            ->post(route('account.competitor-site-groups.store'), [
                'competitor_group_name' => 'Nhóm A',
                'competitor_site_ids' => [$site1->id, $site2->id],
            ])
            ->assertRedirect(route('account'));

        $group = CompetitorSiteGroup::query()->where('user_id', $user->id)->where('name', 'Nhóm A')->first();
        $this->assertNotNull($group);
        $this->assertSame([$site1->id, $site2->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($user)
            ->put(route('account.competitor-site-groups.update', $group), [
                'competitor_group_name' => 'Nhóm B',
                'competitor_site_ids' => [$site3->id],
            ])
            ->assertRedirect(route('account'));

        $group->refresh();
        $this->assertSame('Nhóm B', $group->name);
        $this->assertSame([$site3->id], $group->competitorSites()->pluck('competitor_sites.id')->all());

        $this->actingAs($user)
            ->delete(route('account.competitor-site-groups.destroy', $group))
            ->assertRedirect(route('account'));

        $this->assertDatabaseMissing('competitor_site_groups', ['id' => $group->id]);
    }
}
