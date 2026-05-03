<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_nav_is_hidden_for_subuser_but_visible_for_owner(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'allow_shopee_check' => true,
        ]);
        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('dashboard.competitors'), false);

        $this->actingAs($subUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('dashboard.competitors'), false)
            ->assertSee(route('dashboard.reports'), false)
            ->assertSee(route('shopee.dashboard'), false)
            ->assertSee(route('account'), false);
    }

    public function test_shopee_nav_is_hidden_until_admin_enables_it(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'allow_shopee_check' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('shopee.dashboard'), false);

        $this->actingAs($owner)
            ->get(route('shopee.dashboard'))
            ->assertForbidden();

        $owner->update(['allow_shopee_check' => true]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('shopee.dashboard'), false);

        $this->actingAs($owner)
            ->get(route('shopee.dashboard'))
            ->assertOk();
    }
}
