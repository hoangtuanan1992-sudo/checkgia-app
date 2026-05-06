<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeAgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_requires_api_key(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);

        $this->postJson('/api/scrape-agent/heartbeat', [
            'agentId' => 'windows-pc-01',
        ])->assertStatus(401);
    }

    public function test_agent_can_heartbeat(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);

        $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/heartbeat', [
                'agentId' => 'windows-pc-01',
                'agentName' => 'May quet nha',
                'version' => '1.0.0',
                'status' => 'online',
                'capabilities' => [
                    'http' => true,
                    'browser' => true,
                    'javascript' => true,
                    'variants' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['serverTime', 'config']);

        $this->assertDatabaseHas('scrape_agents', [
            'agent_id' => 'windows-pc-01',
            'name' => 'May quet nha',
            'status' => 'online',
        ]);
    }

    public function test_agent_can_use_api_key_saved_in_admin_settings(): void
    {
        config(['services.checkgia_agent.api_key' => '']);
        AppSetting::query()->create([
            'windows_agent_api_key' => 'db-agent-secret-123456',
        ]);

        $this->withHeader('Authorization', 'Bearer db-agent-secret-123456')
            ->postJson('/api/scrape-agent/heartbeat', [
                'agentId' => 'windows-pc-01',
                'agentName' => 'May quet nha',
                'version' => '1.0.0',
                'status' => 'online',
                'capabilities' => [
                    'http' => true,
                    'browser' => true,
                    'javascript' => true,
                    'variants' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('scrape_agents', [
            'agent_id' => 'windows-pc-01',
        ]);
    }

    public function test_agent_can_lease_product_and_competitor_jobs(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);
        [$product, $competitor] = $this->makeProductWithCompetitor();

        $response = $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/jobs/lease', [
                'agentId' => 'windows-pc-01',
                'limit' => 5,
                'capabilities' => [
                    'http' => true,
                    'browser' => true,
                    'javascript' => true,
                    'variants' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $jobs = $response->json('jobs');
        $this->assertCount(2, $jobs);
        $this->assertEquals($product->id, $jobs[0]['productId']);
        $this->assertEquals($competitor->id, $jobs[1]['competitorId']);
        $this->assertNotEmpty($jobs[0]['leaseToken']);

        $this->assertDatabaseCount('scrape_agent_jobs', 2);
    }

    public function test_agent_result_updates_competitor_price(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);
        [, $competitor] = $this->makeProductWithCompetitor();
        $job = $this->leaseJob('competitor');

        $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/jobs/result', [
                'agentId' => 'windows-pc-01',
                'jobId' => $job['jobId'],
                'leaseToken' => $job['leaseToken'],
                'competitorId' => $competitor->id,
                'productId' => $competitor->product_id,
                'url' => $competitor->url,
                'ok' => true,
                'status' => 'success',
                'result' => [
                    'name' => 'Competitor iPhone',
                    'price' => 12340000,
                    'priceText' => '12.340.000d',
                    'currency' => 'VND',
                    'url' => $competitor->url,
                    'fetchedAt' => now()->toIso8601String(),
                    'method' => 'http',
                    'source' => 'windows-agent',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 12340000,
        ]);
        $this->assertNull($competitor->fresh()->price_missing_at);
    }

    public function test_agent_no_price_marks_competitor_price_missing(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);
        [, $competitor] = $this->makeProductWithCompetitor();
        $job = $this->leaseJob('competitor');

        $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/jobs/result', [
                'agentId' => 'windows-pc-01',
                'jobId' => $job['jobId'],
                'leaseToken' => $job['leaseToken'],
                'competitorId' => $competitor->id,
                'productId' => $competitor->product_id,
                'url' => $competitor->url,
                'ok' => true,
                'status' => 'no_price',
                'result' => [
                    'name' => 'Competitor iPhone',
                    'price' => null,
                    'priceText' => null,
                    'reason' => 'Lien he',
                    'url' => $competitor->url,
                    'fetchedAt' => now()->toIso8601String(),
                ],
            ])
            ->assertOk();

        $this->assertNotNull($competitor->fresh()->price_missing_at);
        $this->assertDatabaseMissing('competitor_prices', [
            'competitor_id' => $competitor->id,
            'price' => 0,
        ]);
    }

    public function test_agent_result_updates_own_product(): void
    {
        config(['services.checkgia_agent.api_key' => 'agent-secret']);
        [$product] = $this->makeProductWithCompetitor();
        $job = $this->leaseJob('product');

        $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/jobs/result', [
                'agentId' => 'windows-pc-01',
                'jobId' => $job['jobId'],
                'leaseToken' => $job['leaseToken'],
                'productId' => $product->id,
                'url' => $product->product_url,
                'ok' => true,
                'status' => 'success',
                'result' => [
                    'name' => 'Own Product Updated',
                    'price' => 9900000,
                    'priceText' => '9.900.000d',
                    'currency' => 'VND',
                    'url' => $product->product_url,
                    'fetchedAt' => now()->toIso8601String(),
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Own Product Updated',
            'price' => 9900000,
        ]);
        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $product->id,
            'price' => 9900000,
        ]);
    }

    /**
     * @return array{0: Product, 1: Competitor}
     */
    private function makeProductWithCompetitor(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'user_id' => $user->id,
            'name' => 'Own Product',
            'price' => 10000000,
            'product_url' => 'https://own.example.com/product-a',
        ]);
        $site = CompetitorSite::query()->create([
            'user_id' => $user->id,
            'name' => 'competitor.example.com',
            'domain' => 'competitor.example.com',
        ]);
        $competitor = Competitor::query()->create([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
            'name' => $site->name,
            'url' => 'https://competitor.example.com/product-a',
        ]);

        return [$product, $competitor];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaseJob(string $type): array
    {
        $response = $this->withHeader('Authorization', 'Bearer agent-secret')
            ->postJson('/api/scrape-agent/jobs/lease', [
                'agentId' => 'windows-pc-01',
                'limit' => 5,
                'capabilities' => [
                    'http' => true,
                    'browser' => true,
                    'javascript' => true,
                    'variants' => true,
                ],
            ])
            ->assertOk();

        $jobs = collect($response->json('jobs'));
        $job = $jobs->firstWhere('type', $type);
        $this->assertIsArray($job);

        return $job;
    }
}
