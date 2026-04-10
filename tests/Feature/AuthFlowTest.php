<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_register_action_is_disabled(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_on_failed_attempts(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect('/login')->assertSessionHasErrors('email');
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Bạn đăng nhập quá nhiều lần', session('errors')->first('email'));
    }
}
