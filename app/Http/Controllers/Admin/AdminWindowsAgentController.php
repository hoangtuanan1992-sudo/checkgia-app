<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrapeAgent;
use App\Models\ScrapeAgentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminWindowsAgentController extends Controller
{
    public function index(): View
    {
        $migrated = Schema::hasTable('scrape_agents') && Schema::hasTable('scrape_agent_jobs');
        if (! $migrated) {
            return view('admin.windows-agent.index', [
                'migrated' => false,
                'agents' => collect(),
                'stats' => $this->emptyStats(),
                'activeJobs' => collect(),
                'pendingJobs' => collect(),
                'recentJobs' => collect(),
                'domainStats' => collect(),
                'onlineCutoff' => now()->subMinutes(2),
            ]);
        }

        $onlineCutoff = now()->subSeconds(max(90, ((int) config('services.checkgia_agent.poll_interval_seconds', 10) * 4) + 60));
        $today = now()->startOfDay();
        $hasCompletedAgentColumn = Schema::hasColumn('scrape_agent_jobs', 'completed_by_agent_id');

        $statusCounts = ScrapeAgentJob::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalJobs = (int) $statusCounts->sum();
        $finishedJobs = (int) (($statusCounts['done'] ?? 0) + ($statusCounts['failed'] ?? 0));
        $completionPercent = $totalJobs > 0 ? round(($finishedJobs / $totalJobs) * 100, 1) : 0.0;

        $activeByAgent = ScrapeAgentJob::query()
            ->where('status', 'leased')
            ->whereNotNull('leased_by_agent_id')
            ->select('leased_by_agent_id', DB::raw('count(*) as total'))
            ->groupBy('leased_by_agent_id')
            ->pluck('total', 'leased_by_agent_id');

        $doneTodayByAgent = $hasCompletedAgentColumn
            ? ScrapeAgentJob::query()
                ->where('status', 'done')
                ->where('finished_at', '>=', $today)
                ->whereNotNull('completed_by_agent_id')
                ->select('completed_by_agent_id', DB::raw('count(*) as total'))
                ->groupBy('completed_by_agent_id')
                ->pluck('total', 'completed_by_agent_id')
            : collect();

        $failedTodayByAgent = $hasCompletedAgentColumn
            ? ScrapeAgentJob::query()
                ->where('status', 'failed')
                ->where('finished_at', '>=', $today)
                ->whereNotNull('completed_by_agent_id')
                ->select('completed_by_agent_id', DB::raw('count(*) as total'))
                ->groupBy('completed_by_agent_id')
                ->pluck('total', 'completed_by_agent_id')
            : collect();

        $agents = ScrapeAgent::query()
            ->orderByRaw('last_seen_at is null asc')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (ScrapeAgent $agent) use ($onlineCutoff, $activeByAgent, $doneTodayByAgent, $failedTodayByAgent) {
                $agentId = (string) $agent->agent_id;
                $agent->is_online = $agent->last_seen_at && $agent->last_seen_at->gte($onlineCutoff);
                $agent->active_jobs_count = (int) ($activeByAgent[$agentId] ?? 0);
                $agent->done_today_count = (int) ($doneTodayByAgent[$agentId] ?? 0);
                $agent->failed_today_count = (int) ($failedTodayByAgent[$agentId] ?? 0);

                return $agent;
            });

        $activeJobs = ScrapeAgentJob::query()
            ->with([
                'product:id,name,user_id',
                'competitor:id,name,product_id,competitor_site_id',
                'competitorSite:id,name,domain',
            ])
            ->where('status', 'leased')
            ->orderBy('lease_expires_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $pendingJobs = ScrapeAgentJob::query()
            ->with([
                'product:id,name,user_id',
                'competitor:id,name,product_id,competitor_site_id',
                'competitorSite:id,name,domain',
            ])
            ->where('status', 'pending')
            ->orderBy('priority')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $recentJobs = ScrapeAgentJob::query()
            ->with([
                'product:id,name,user_id',
                'competitor:id,name,product_id,competitor_site_id',
                'competitorSite:id,name,domain',
            ])
            ->whereIn('status', ['done', 'failed'])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $domainStats = ScrapeAgentJob::query()
            ->select('domain', 'status', DB::raw('count(*) as total'))
            ->whereNotNull('domain')
            ->groupBy('domain', 'status')
            ->orderBy('domain')
            ->get()
            ->groupBy('domain')
            ->map(function ($rows, string $domain) {
                $counts = $rows->pluck('total', 'status');

                return [
                    'domain' => $domain,
                    'pending' => (int) ($counts['pending'] ?? 0),
                    'leased' => (int) ($counts['leased'] ?? 0),
                    'done' => (int) ($counts['done'] ?? 0),
                    'failed' => (int) ($counts['failed'] ?? 0),
                    'total' => (int) $counts->sum(),
                ];
            })
            ->sortByDesc('total')
            ->take(30)
            ->values();

        return view('admin.windows-agent.index', [
            'migrated' => true,
            'agents' => $agents,
            'stats' => [
                'agents_total' => $agents->count(),
                'agents_online' => $agents->where('is_online', true)->count(),
                'jobs_total' => $totalJobs,
                'jobs_pending' => (int) ($statusCounts['pending'] ?? 0),
                'jobs_leased' => (int) ($statusCounts['leased'] ?? 0),
                'jobs_done' => (int) ($statusCounts['done'] ?? 0),
                'jobs_failed' => (int) ($statusCounts['failed'] ?? 0),
                'completion_percent' => $completionPercent,
            ],
            'activeJobs' => $activeJobs,
            'pendingJobs' => $pendingJobs,
            'recentJobs' => $recentJobs,
            'domainStats' => $domainStats,
            'onlineCutoff' => $onlineCutoff,
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyStats(): array
    {
        return [
            'agents_total' => 0,
            'agents_online' => 0,
            'jobs_total' => 0,
            'jobs_pending' => 0,
            'jobs_leased' => 0,
            'jobs_done' => 0,
            'jobs_failed' => 0,
            'completion_percent' => 0.0,
        ];
    }
}
