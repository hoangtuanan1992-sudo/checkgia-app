<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerScanRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_scan_for_missing_website(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/dashboard/quick-scan/request', [
                'website_url' => 'https://example.com/',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/dashboard/quick-scan', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('scanner_scan_requests', [
            'requested_by_user_id' => $user->id,
            'requested_url' => 'https://example.com',
            'url_key' => 'example.com',
            'status' => 'pending',
        ]);
    }

    public function test_windows_scanner_can_pull_and_accept_scan_request(): void
    {
        config(['services.checkgia_import.api_key' => 'secret-key']);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/dashboard/quick-scan/request', [
            'website_url' => 'https://example.com/',
        ]);

        $nextResponse = $this->withHeader('Authorization', 'Bearer secret-key')
            ->getJson('/api/scan-requests/next');

        $nextResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.url', 'https://example.com');

        $requestId = (int) $nextResponse->json('request.id');

        $this->withHeader('x-api-key', 'secret-key')
            ->postJson('/api/scan-requests/'.$requestId.'/accepted', [
                'externalJobId' => 'scanner-job-1',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('scanner_scan_requests', [
            'id' => $requestId,
            'status' => 'running',
            'external_job_id' => 'scanner-job-1',
        ]);
    }
}
