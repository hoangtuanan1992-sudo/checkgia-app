<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserShopeePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_shopee_check_for_owner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create([
            'role' => 'owner',
            'allow_shopee_check' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'owner',
                'parent_user_id' => '',
                'service_start_date' => '',
                'service_end_date' => '',
                'allow_shopee_check' => '1',
                'admin_note' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue((bool) $owner->fresh()->allow_shopee_check);
    }
}
