@extends('layouts.app')

@section('content')
    <div class="card history-page" style="max-width:980px">
        <div class="card-header">
            <h1 class="card-title">Lịch sử giá: {{ $competitor->name }}</h1>
            <p class="card-sub">Sản phẩm: {{ $product->name }}</p>
        </div>
        <div class="card-body">
            <div class="actions" style="justify-content:space-between;margin-top:0">
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn btn-secondary" href="{{ route('competitors.history', $competitor) }}?days=7">7 ngày</a>
                    <a class="btn btn-secondary" href="{{ route('competitors.history', $competitor) }}?days=30">30 ngày</a>
                    <a class="btn btn-secondary" href="{{ route('competitors.history', $competitor) }}?days=90">90 ngày</a>
                    <span class="pill">{{ $days }} ngày</span>
                </div>
                <a class="btn btn-secondary" href="{{ route('products.history', $product) }}?days={{ $days }}">Giá của bạn</a>
            </div>

            @php
                $ownPoints = ($ownHistory ?? collect())->map(fn ($p) => [
                    'x' => $p->fetched_at?->timestamp ? $p->fetched_at->timestamp * 1000 : null,
                    'y' => (int) $p->price,
                ])->filter(fn ($pt) => ! is_null($pt['x']))->values();

                $seriesCompetitors = $competitors->mapWithKeys(function ($c) {
                    $points = $c->prices
                        ->map(fn ($p) => [
                            'x' => $p->fetched_at?->timestamp ? $p->fetched_at->timestamp * 1000 : null,
                            'y' => (int) $p->price,
                        ])
                        ->filter(fn ($pt) => ! is_null($pt['x']))
                        ->values();

                    return [
                        (string) $c->id => [
                            'name' => $c->name,
                            'data' => $points,
                        ],
                    ];
                });

                $series = collect([
                    'own' => ['name' => 'Giá của bạn', 'data' => $ownPoints],
                ])->union($seriesCompetitors);
            @endphp

            <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                <div class="card-header" style="padding:16px 16px 6px">
                    <h2 class="card-title" style="font-size:18px">Biểu đồ so sánh</h2>
                    <p class="card-sub">Chọn các bên để hiển thị trên biểu đồ</p>
                </div>
                <div class="card-body" style="padding:8px 16px 16px">
                    <div class="history-series-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
                        <label class="history-series-item" style="display:flex;gap:8px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff">
                            <input type="checkbox" class="js-series-toggle" value="own" checked>
                            <span style="font-size:14px">Giá của bạn</span>
                        </label>
                        @foreach($competitors as $c)
                            <label class="history-series-item" style="display:flex;gap:8px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff">
                                <input type="checkbox" class="js-series-toggle" value="{{ $c->id }}" @checked($c->id === $competitor->id)>
                                <span style="font-size:14px">{{ $c->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div style="margin-top:14px">
                        <canvas id="compareChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:14px">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prices as $row)
                            <tr>
                                <td>{{ $row->fetched_at?->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</td>
                                <td>{{ number_format($row->price, 0, ',', '.') }}đ</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="hint" colspan="2">Chưa có dữ liệu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const canvas = document.getElementById('compareChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const series = @json($series);
            const palette = ['#0d6efd', '#16a34a', '#dc3545', '#7c3aed', '#ea580c', '#0891b2', '#0f766e', '#b91c1c'];

            function formatTime(ts) {
                const d = new Date(ts);
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const hh = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                return `${dd}/${mm} ${hh}:${mi}`;
            }

            function buildDatasets(selectedIds) {
                return selectedIds
                    .filter((id) => series[id] && Array.isArray(series[id].data) && series[id].data.length)
                    .map((id, idx) => ({
                        label: series[id].name,
                        data: series[id].data,
                        borderColor: palette[idx % palette.length],
                        backgroundColor: 'transparent',
                        tension: 0.2,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                    }));
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: { datasets: [] },
                options: {
                    parsing: true,
                    plugins: {
                        legend: { labels: { color: '#111827' } },
                        tooltip: {
                            callbacks: {
                                title: (items) => (items[0] ? formatTime(items[0].parsed.x) : ''),
                                label: (item) => `${item.dataset.label}: ${new Intl.NumberFormat('vi-VN').format(item.parsed.y)}đ`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            ticks: {
                                color: '#6b7280',
                                callback: (v) => formatTime(v),
                                maxRotation: 0,
                            },
                            grid: { color: 'rgba(17,24,39,.06)' },
                        },
                        y: {
                            ticks: {
                                color: '#6b7280',
                                callback: (v) => `${new Intl.NumberFormat('vi-VN').format(v)}đ`,
                            },
                            grid: { color: 'rgba(17,24,39,.06)' },
                        },
                    },
                },
            });

            function syncChart() {
                const selected = Array.from(document.querySelectorAll('.js-series-toggle'))
                    .filter((el) => el.checked)
                    .map((el) => el.value);

                chart.data.datasets = buildDatasets(selected);
                chart.update();
            }

            document.querySelectorAll('.js-series-toggle').forEach((el) => {
                el.addEventListener('change', syncChart);
            });

            syncChart();
        })();
    </script>
@endsection
