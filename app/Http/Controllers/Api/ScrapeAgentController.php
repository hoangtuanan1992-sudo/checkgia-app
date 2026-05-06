<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScrapeAgent;
use App\Models\ScrapeAgentJob;
use App\Services\ScrapeAgentJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScrapeAgentController extends Controller
{
    public function __construct(private readonly ScrapeAgentJobService $jobs) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $auth = $this->authorizeAgent($request);
        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'agentId' => ['required', 'string', 'max:128'],
            'agentName' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:30'],
            'capabilities' => ['nullable', 'array'],
        ]);

        ScrapeAgent::query()->updateOrCreate(
            ['agent_id' => (string) $data['agentId']],
            [
                'name' => trim((string) ($data['agentName'] ?? '')) ?: null,
                'version' => trim((string) ($data['version'] ?? '')) ?: null,
                'status' => trim((string) ($data['status'] ?? 'online')) ?: 'online',
                'capabilities' => $data['capabilities'] ?? [],
                'last_seen_at' => now(),
                'last_heartbeat_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'serverTime' => now()->toIso8601String(),
            'config' => [
                'pollIntervalSeconds' => (int) config('services.checkgia_agent.poll_interval_seconds', 10),
                'maxConcurrentJobs' => (int) config('services.checkgia_agent.max_concurrent_jobs', 3),
                'leaseSeconds' => (int) config('services.checkgia_agent.lease_seconds', 900),
            ],
        ]);
    }

    public function lease(Request $request): JsonResponse
    {
        $auth = $this->authorizeAgent($request);
        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'agentId' => ['required', 'string', 'max:128'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'capabilities' => ['nullable', 'array'],
        ]);

        ScrapeAgent::query()->updateOrCreate(
            ['agent_id' => (string) $data['agentId']],
            [
                'status' => 'online',
                'capabilities' => $data['capabilities'] ?? [],
                'last_seen_at' => now(),
                'last_lease_at' => now(),
            ]
        );

        $jobs = $this->jobs->lease(
            (string) $data['agentId'],
            (int) ($data['limit'] ?? 1),
            $data['capabilities'] ?? []
        );

        return response()->json([
            'ok' => true,
            'jobs' => $jobs,
        ]);
    }

    public function extend(Request $request): JsonResponse
    {
        $auth = $this->authorizeAgent($request);
        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'agentId' => ['required', 'string', 'max:128'],
            'jobId' => ['required', 'string', 'max:64'],
            'leaseToken' => ['required', 'string', 'max:100'],
            'extendSeconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
        ]);

        $job = ScrapeAgentJob::query()
            ->where('job_uuid', (string) $data['jobId'])
            ->where('leased_by_agent_id', (string) $data['agentId'])
            ->where('lease_token', (string) $data['leaseToken'])
            ->where('status', 'leased')
            ->first();

        if (! $job) {
            return response()->json([
                'ok' => false,
                'error' => 'Lease not found.',
            ], 404);
        }

        $extendSeconds = max(60, min(3600, (int) ($data['extendSeconds'] ?? config('services.checkgia_agent.lease_seconds', 900))));
        $job->forceFill([
            'lease_expires_at' => now()->addSeconds($extendSeconds),
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function result(Request $request): JsonResponse
    {
        $auth = $this->authorizeAgent($request);
        if ($auth) {
            return $auth;
        }

        $validator = Validator::make($request->all(), [
            'agentId' => ['required', 'string', 'max:128'],
            'jobId' => ['required', 'string', 'max:64'],
            'leaseToken' => ['required', 'string', 'max:100'],
            'competitorId' => ['nullable', 'integer'],
            'competitorSiteId' => ['nullable', 'integer'],
            'productId' => ['nullable', 'integer'],
            'url' => ['required', 'string', 'max:2048'],
            'variantKey' => ['nullable', 'string', 'max:255'],
            'ok' => ['required', 'boolean'],
            'status' => ['required', 'string', 'in:success,no_price,failed,variants_found'],
            'result' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'error' => ['nullable', 'array'],
            'error.code' => ['nullable', 'string', 'max:80'],
            'error.message' => ['nullable', 'string', 'max:4000'],
            'error.retryable' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        ScrapeAgent::query()->where('agent_id', (string) $data['agentId'])->update([
            'status' => 'online',
            'last_seen_at' => now(),
            'last_result_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->jobs->recordResult($data);
        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'],
            ], (int) $result['status']);
        }

        return response()->json(['ok' => true]);
    }

    private function authorizeAgent(Request $request): ?JsonResponse
    {
        $expectedKey = trim((string) config('services.checkgia_agent.api_key', ''));
        if ($expectedKey === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Scrape agent API key is not configured.',
            ], 503);
        }

        $providedKey = $this->readApiKey($request);
        if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid API key.',
            ], 401);
        }

        return null;
    }

    private function readApiKey(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return trim((string) $request->header('x-api-key', ''));
    }
}
