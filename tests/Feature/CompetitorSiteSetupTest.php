<?php

namespace Tests\Feature;

use App\Models\CompetitorSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorSiteSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_competitor_site_normalizes_product_url_to_domain(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('dashboard.competitors.sites.store'), [
                'name' => 'https://viettelstore.vn/may-tinh-bang/samsung-galaxy-tab-s11-5g-12gb-128gb-pid355665.html',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competitor_sites', [
            'user_id' => $user->id,
            'name' => 'viettelstore.vn',
        ]);
        $this->assertDatabaseMissing('competitor_sites', [
            'user_id' => $user->id,
            'name' => 'https://viettelstore.vn/may-tinh-bang/samsung-galaxy-tab-s11-5g-12gb-128gb-pid355665.html',
        ]);
    }

    public function test_store_competitor_site_keeps_www_host_when_present(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('dashboard.competitors.sites.store'), [
                'name' => 'https://www.topzone.vn/',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competitor_sites', [
            'user_id' => $user->id,
            'name' => 'www.topzone.vn',
        ]);
    }

    public function test_competitor_settings_page_cleans_existing_url_names(): void
    {
        $user = User::factory()->create();

        CompetitorSite::create([
            'user_id' => $user->id,
            'name' => 'viettelstore.vn',
            'position' => 1,
        ]);
        CompetitorSite::create([
            'user_id' => $user->id,
            'name' => 'https://viettelstore.vn/may-tinh-bang/samsung-galaxy-tab-s11-5g-12gb-128gb-pid355665.html',
            'position' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.competitors'))
            ->assertOk();

        $this->assertSame(1, CompetitorSite::query()
            ->where('user_id', $user->id)
            ->where('name', 'viettelstore.vn')
            ->count());
        $this->assertDatabaseMissing('competitor_sites', [
            'user_id' => $user->id,
            'name' => 'https://viettelstore.vn/may-tinh-bang/samsung-galaxy-tab-s11-5g-12gb-128gb-pid355665.html',
        ]);
    }
}
