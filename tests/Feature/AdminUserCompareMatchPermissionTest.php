<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserCompareMatchPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_compare_match_for_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create([
            'role' => 'owner',
            'allow_compare_match' => false,
            'product_limit' => 100,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'parent_user_id' => '',
                'service_start_date' => '',
                'service_end_date' => '',
                'product_limit' => 100,
                'allow_compare_match' => '1',
                'admin_note' => '',
                'scrape_schedule_times' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue((bool) $owner->fresh()->allow_compare_match);
    }
}
