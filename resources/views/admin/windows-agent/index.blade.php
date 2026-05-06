@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($value) => number_format((int) $value, 0, ',', '.');
        $time = fn ($value) => $value ? $value->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') : '-';
        $shortUrl = function (?string $url): string {
            $url = trim((string) $url);
            if ($url === '') {
                return '-';
            }

            return mb_strlen($url) > 86 ? mb_substr($url, 0, 83).'...' : $url;
        };
        $statusLabel = [
            'pending' => 'Đang chờ',
            'leased' => 'Đang quét',
            'done' => 'Hoàn tất',
            'failed' => 'Lỗi',
        ];
        $typeLabel = [
            'product' => 'Sản phẩm của bạn',
            'competitor' => 'Link đối thủ',
        ];
        $completion = max(0, min(100, (float) ($stats['completion_percent'] ?? 0)));
    @endphp

    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Windows Agent</h1>
                    <p class="card-sub">Theo dõi máy Windows quét phụ, job đang chạy và tiến độ xử lý.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Admin</a>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="btn" href="{{ route('admin.windows-agent.index') }}">Làm mới</a>
                </div>
            </div>

            <div class="card-body">
                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0;margin-bottom:14px">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">Key kết nối Windows Agent</h2>
                        <p class="card-sub">Key này phải giống với trường <code>apiKey</code> trong file <code>config.json</code> của phần mềm Windows.</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        @if($canStoreApiKey)
                            <form method="POST" action="{{ route('admin.windows-agent.api-key.update') }}" style="display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:10px;align-items:flex-end">
                                @csrf
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="windows_agent_api_key">Windows Agent API key</label>
                                    <input
                                        class="input"
                                        id="windows_agent_api_key"
                                        name="windows_agent_api_key"
                                        type="password"
                                        value=""
                                        placeholder="{{ $hasDatabaseApiKey ? 'Đã lưu key, nhập để đổi key mới' : 'VD: ckg_agent_9f7Kp2xQm88sLw2026' }}"
                                        autocomplete="new-password"
                                    >
                                    @error('windows_agent_api_key')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">
                                        Trạng thái:
                                        @if($hasDatabaseApiKey)
                                            <b style="color:#166534">Đã lưu key trong Admin</b>
                                        @else
                                            <b style="color:#991b1b">Chưa lưu key trong Admin</b>
                                        @endif
                                        @if($envAgentApiKeyConfigured)
                                            <span> - Có key dự phòng trong <code>.env</code></span>
                                        @endif
                                    </div>
                                </div>
                                <button class="btn" type="submit" style="height:44px">Lưu key</button>
                            </form>
                        @else
                            <div style="border:1px solid rgba(220,53,69,.28);background:rgba(220,53,69,.08);border-radius:14px;padding:14px">
                                <div style="font-weight:800;color:#991b1b">Chưa có cột lưu key Windows Agent.</div>
                                <div class="hint" style="margin-top:6px">Hãy chạy migration trên hosting: <code>php artisan migrate --force</code></div>
                            </div>
                        @endif
                    </div>
                </div>

                @if(! $migrated)
                    <div style="border:1px solid rgba(220,53,69,.28);background:rgba(220,53,69,.08);border-radius:14px;padding:14px">
                        <div style="font-weight:800;color:#991b1b">Chưa có bảng Windows Agent.</div>
                        <div class="hint" style="margin-top:6px">Hãy chạy migration trên hosting: <code>php artisan migrate --force</code></div>
                    </div>
                @else
                    <div class="agent-metric-grid">
                        <div class="agent-metric">
                            <div class="label">Máy đang kết nối</div>
                            <div class="agent-metric-value">{{ $fmt($stats['agents_online']) }}/{{ $fmt($stats['agents_total']) }}</div>
                        </div>
                        <div class="agent-metric">
                            <div class="label">Job đang quét</div>
                            <div class="agent-metric-value">{{ $fmt($stats['jobs_leased']) }}</div>
                        </div>
                        <div class="agent-metric">
                            <div class="label">Job đang chờ</div>
                            <div class="agent-metric-value">{{ $fmt($stats['jobs_pending']) }}</div>
                        </div>
                        <div class="agent-metric">
                            <div class="label">Hoàn tất / lỗi</div>
                            <div class="agent-metric-value">{{ $fmt($stats['jobs_done']) }} / {{ $fmt($stats['jobs_failed']) }}</div>
                        </div>
                    </div>

                    <div class="agent-progress-wrap">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
                            <div style="font-weight:800">Mức hoàn thành queue hiện tại</div>
                            <div style="font-weight:900;color:var(--accent)">{{ number_format($completion, 1, ',', '.') }}%</div>
                        </div>
                        <div class="agent-progress">
                            <div style="width:{{ $completion }}%"></div>
                        </div>
                        <div class="hint" style="margin-top:8px">
                            Tổng job: {{ $fmt($stats['jobs_total']) }}. Hoàn thành được tính bằng job đã xong hoặc lỗi trên tổng job đang có trong queue.
                        </div>
                    </div>

                    <section style="margin-top:16px">
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-end">
                            <div>
                                <h2 class="agent-section-title">Máy Windows đang kết nối</h2>
                                <div class="hint" style="margin-top:4px">Máy online là máy có heartbeat sau {{ $time($onlineCutoff) }}.</div>
                            </div>
                        </div>
                        <div class="table-wrap" style="margin-top:10px;max-height:420px">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="min-width:190px">Agent ID</th>
                                        <th style="min-width:180px">Tên máy</th>
                                        <th style="width:110px">Trạng thái</th>
                                        <th style="width:100px">Đang quét</th>
                                        <th style="width:130px">Xong hôm nay</th>
                                        <th style="width:120px">Lỗi hôm nay</th>
                                        <th style="min-width:170px">Heartbeat cuối</th>
                                        <th style="min-width:170px">Nhận job cuối</th>
                                        <th style="min-width:170px">Gửi kết quả cuối</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($agents as $agent)
                                        <tr>
                                            <td style="font-weight:800">{{ $agent->agent_id }}</td>
                                            <td>
                                                {{ $agent->name ?: '-' }}
                                                @if($agent->version)
                                                    <div class="hint" style="margin-top:4px">v{{ $agent->version }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="agent-badge {{ $agent->is_online ? 'agent-badge-ok' : 'agent-badge-muted' }}">
                                                    {{ $agent->is_online ? 'Online' : 'Offline' }}
                                                </span>
                                            </td>
                                            <td style="font-weight:800">{{ $fmt($agent->active_jobs_count) }}</td>
                                            <td style="font-weight:800;color:#166534">{{ $fmt($agent->done_today_count) }}</td>
                                            <td style="font-weight:800;color:#991b1b">{{ $fmt($agent->failed_today_count) }}</td>
                                            <td>{{ $time($agent->last_heartbeat_at) }}</td>
                                            <td>{{ $time($agent->last_lease_at) }}</td>
                                            <td>{{ $time($agent->last_result_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" style="text-align:center;color:var(--muted)">Chưa có máy Windows Agent nào kết nối.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section style="margin-top:18px">
                        <h2 class="agent-section-title">Đang quét</h2>
                        <div class="table-wrap" style="margin-top:10px;max-height:430px">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:80px">ID</th>
                                        <th style="min-width:150px">Máy nhận</th>
                                        <th style="min-width:150px">Loại job</th>
                                        <th style="min-width:260px">Sản phẩm</th>
                                        <th style="min-width:170px">Đối thủ/domain</th>
                                        <th style="min-width:300px">URL</th>
                                        <th style="width:100px">Lần thử</th>
                                        <th style="min-width:160px">Hết lease</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($activeJobs as $job)
                                        <tr>
                                            <td>#{{ $job->id }}</td>
                                            <td style="font-weight:800">{{ $job->leased_by_agent_id ?: '-' }}</td>
                                            <td>{{ $typeLabel[$job->type] ?? $job->type }}</td>
                                            <td>
                                                {{ $job->product?->name ?: '-' }}
                                                @if($job->product_id)
                                                    <div class="hint" style="margin-top:4px">Product ID: {{ $job->product_id }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $job->competitorSite?->name ?: ($job->domain ?: '-') }}</td>
                                            <td><a href="{{ $job->url }}" target="_blank" rel="noopener">{{ $shortUrl($job->url) }}</a></td>
                                            <td>{{ $fmt($job->attempts) }}</td>
                                            <td>{{ $time($job->lease_expires_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" style="text-align:center;color:var(--muted)">Hiện không có job nào đang được Windows Agent quét.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="agent-two-col">
                        <section>
                            <h2 class="agent-section-title">Job đang chờ giao</h2>
                            <div class="table-wrap" style="margin-top:10px;max-height:430px">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width:80px">ID</th>
                                            <th style="width:120px">Loại</th>
                                            <th style="min-width:240px">Sản phẩm</th>
                                            <th style="min-width:160px">Domain</th>
                                            <th style="width:90px">Ưu tiên</th>
                                            <th style="width:90px">Lần thử</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pendingJobs as $job)
                                            <tr>
                                                <td>#{{ $job->id }}</td>
                                                <td>{{ $typeLabel[$job->type] ?? $job->type }}</td>
                                                <td>{{ $job->product?->name ?: '-' }}</td>
                                                <td>{{ $job->domain ?: '-' }}</td>
                                                <td>{{ $fmt($job->priority) }}</td>
                                                <td>{{ $fmt($job->attempts) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" style="text-align:center;color:var(--muted)">Không còn job đang chờ.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section>
                            <h2 class="agent-section-title">Phân bổ theo domain</h2>
                            <div class="table-wrap" style="margin-top:10px;max-height:430px">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="min-width:180px">Domain</th>
                                            <th>Chờ</th>
                                            <th>Đang quét</th>
                                            <th>Xong</th>
                                            <th>Lỗi</th>
                                            <th>Tổng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($domainStats as $domain)
                                            <tr>
                                                <td style="font-weight:800">{{ $domain['domain'] }}</td>
                                                <td>{{ $fmt($domain['pending']) }}</td>
                                                <td>{{ $fmt($domain['leased']) }}</td>
                                                <td>{{ $fmt($domain['done']) }}</td>
                                                <td>{{ $fmt($domain['failed']) }}</td>
                                                <td>{{ $fmt($domain['total']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" style="text-align:center;color:var(--muted)">Chưa có thống kê domain.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <section style="margin-top:18px">
                        <h2 class="agent-section-title">Kết quả gần nhất</h2>
                        <div class="table-wrap" style="margin-top:10px;max-height:500px">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:80px">ID</th>
                                        <th style="min-width:140px">Trạng thái</th>
                                        <th style="min-width:150px">Máy xử lý</th>
                                        <th style="min-width:150px">Loại job</th>
                                        <th style="min-width:260px">Sản phẩm</th>
                                        <th style="min-width:170px">Đối thủ/domain</th>
                                        <th style="min-width:220px">Lỗi</th>
                                        <th style="min-width:170px">Xong lúc</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentJobs as $job)
                                        <tr>
                                            <td>#{{ $job->id }}</td>
                                            <td>
                                                <span class="agent-badge {{ $job->status === 'done' ? 'agent-badge-ok' : 'agent-badge-danger' }}">
                                                    {{ $statusLabel[$job->status] ?? $job->status }}
                                                </span>
                                            </td>
                                            <td style="font-weight:800">{{ $job->completed_by_agent_id ?: '-' }}</td>
                                            <td>{{ $typeLabel[$job->type] ?? $job->type }}</td>
                                            <td>{{ $job->product?->name ?: '-' }}</td>
                                            <td>{{ $job->competitorSite?->name ?: ($job->domain ?: '-') }}</td>
                                            <td>
                                                @if($job->last_error)
                                                    <span title="{{ $job->last_error }}">{{ $job->last_error_code ?: 'ERROR' }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $time($job->finished_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" style="text-align:center;color:var(--muted)">Chưa có kết quả nào từ Windows Agent.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <style>
        .agent-metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
        .agent-metric{border:1px solid var(--border);border-radius:14px;padding:14px;background:#fff}
        .agent-metric-value{font-size:26px;font-weight:900;margin-top:6px}
        .agent-progress-wrap{margin-top:14px;border:1px solid var(--border);border-radius:14px;padding:14px;background:#fff}
        .agent-progress{height:12px;border-radius:999px;background:#e5e7eb;overflow:hidden;margin-top:10px}
        .agent-progress div{height:100%;border-radius:999px;background:var(--accent)}
        .agent-section-title{font-size:18px;margin:0;font-weight:900}
        .agent-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:800;white-space:nowrap}
        .agent-badge-ok{background:rgba(22,163,74,.12);color:#166534;border:1px solid rgba(22,163,74,.22)}
        .agent-badge-muted{background:#f3f4f6;color:#4b5563;border:1px solid var(--border)}
        .agent-badge-danger{background:rgba(220,53,69,.12);color:#991b1b;border:1px solid rgba(220,53,69,.22)}
        .agent-two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px}
        code{background:#f3f4f6;border:1px solid var(--border);border-radius:8px;padding:2px 6px}
        @media (max-width: 980px){
            .agent-metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .agent-two-col{grid-template-columns:1fr}
        }
        @media (max-width: 640px){
            .agent-metric-grid{grid-template-columns:1fr}
        }
    </style>

    <script>
        setTimeout(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 30000);
    </script>
@endsection
