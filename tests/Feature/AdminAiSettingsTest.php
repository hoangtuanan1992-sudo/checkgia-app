<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_ai_api_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'chatgpt_api_key' => 'sk-test-key',
                'chatgpt_model' => 'gpt-test',
                'gemini_api_key' => 'gemini-test-key',
                'gemini_model' => 'models/gemini-test',
                'grok_api_key' => 'xai-test-key',
                'grok_model' => 'grok-test',
            ])
            ->assertRedirect();

        $setting = AppSetting::current();

        $this->assertSame('sk-test-key', $setting->chatgpt_api_key);
        $this->assertSame('gpt-test', $setting->chatgpt_model);
        $this->assertSame('gemini-test-key', $setting->gemini_api_key);
        $this->assertSame('models/gemini-test', $setting->gemini_model);
        $this->assertSame('xai-test-key', $setting->grok_api_key);
        $this->assertSame('grok-test', $setting->grok_model);
    }

    public function test_admin_can_scan_chatgpt_models(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'gpt-test-a', 'object' => 'model'],
                    ['id' => 'gpt-test-b', 'object' => 'model'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.settings.ai.models', 'chatgpt'), [
                'api_key' => 'sk-test-key',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'models');

        $setting = AppSetting::current();

        $this->assertSame('gpt-test-a', $setting->chatgpt_model);
        $this->assertSame('gpt-test-a', $setting->chatgpt_models[0]['id']);
        $this->assertSame('gpt-test-b', $setting->chatgpt_models[1]['id']);
    }
}
