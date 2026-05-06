<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ScrapeAgent;
use App\Models\ScrapeAgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWindowsAgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_windows_agent_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::query()->create([
            'user_id' => $owner->id,
            'name' => 'iPhone Test',
            'price' => 10000000,
            'product_url' => 'https://own.example.com/iphone-test',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'competitor.example.com',
            'domain' => 'competitor.example.com',
        ]);
        $competitor = Competitor::query()->create([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => $site->name,
            'url' => 'https://competitor.example.com/iphone-test',
        ]);

        ScrapeAgent::query()->create([
            'agent_id' => 'windows-pc-01',
            'name' => 'May quet nha',
            'version' => '1.0.0',
            'status' => 'online',
            'last_seen_at' => now(),
            'last_heartbeat_at' => now(),
            'last_lease_at' => now(),
        ]);
        ScrapeAgentJob::query()->create([
            'job_uuid' => 'job-1',
            'target_key' => 'competitor:'.$competitor->id,
            'type' => 'competitor',
            'product_id' => $product->id,
            'competitor_id' => $competitor->id,
            'competitor_site_id' => $site->id,
            'url' => $competitor->url,
            'domain' => 'competitor.example.com',
            'status' => 'leased',
            'leased_by_agent_id' => 'windows-pc-01',
            'lease_token' => 'lease-token',
            'leased_at' => now(),
            'lease_expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.windows-agent.index'))
            ->assertOk()
            ->assertSee('Windows Agent')
            ->assertSee('windows-pc-01')
            ->assertSee('May quet nha')
            ->assertSee('iPhone Test')
            ->assertSee('competitor.example.com');
    }

    public function test_non_admin_cannot_view_windows_agent_dashboard(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('admin.windows-agent.index'))
            ->assertForbidden();
    }

    public function test_admin_can_save_windows_agent_api_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.windows-agent.api-key.update'), [
                'windows_agent_api_key' => 'db-agent-secret-123456',
            ])
            ->assertRedirect();

        $this->assertSame('db-agent-secret-123456', AppSetting::current()?->windows_agent_api_key);
    }

    public function test_admin_can_create_and_view_windows_agent_test_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.windows-agent.test-jobs.store'), [
                'test_url' => 'https://www.mi.com/vn/product/poco-pad-x1/',
            ])
            ->assertRedirect();

        $job = ScrapeAgentJob::query()->where('type', 'test')->firstOrFail();
        $this->assertSame('mi.com', $job->domain);
        $this->assertStringContainsString('test_job='.$job->job_uuid, $response->headers->get('Location'));

        $this->actingAs($admin)
            ->getJson(route('admin.windows-agent.test-jobs.status', $job))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('job.status', 'pending')
            ->assertJsonPath('job.domain', 'mi.com');
    }
}
