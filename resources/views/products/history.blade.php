@extends('layouts.app')

@section('content')
    <div class="card history-page" style="max-width:980px">
        <div class="card-header">
            <h1 class="card-title">Lịch sử giá: Giá của bạn</h1>
            <p class="card-sub">Sản phẩm: {{ $product->name }}</p>
        </div>
        <div class="card-body">
            <div class="actions" style="justify-content:space-between;margin-top:0">
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn btn-secondary" href="{{ route('products.history', $product) }}?days=7">7 ngày</a>
                    <a class="btn btn-secondary" href="{{ route('products.history', $product) }}?days=30">30 ngày</a>
                    <a class="btn btn-secondary" href="{{ route('products.history', $product) }}?days=90">90 ngày</a>
                    <span class="pill">{{ $days }} ngày</span>
                </div>
                <a class="btn btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
            </div>

            <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                <div class="card-header" style="padding:16px 16px 6px">
                    <h2 class="card-title" style="font-size:18px">Biểu đồ</h2>
                </div>
                <div class="card-body" style="padding:8px 16px 16px">
                    <canvas id="ownChart" height="140"></canvas>
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
                        @forelse($history as $row)
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
            const canvas = document.getElementById('ownChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const points = @json($history->map(fn($p) => ['x' => $p->fetched_at?->timestamp ? $p->fetched_at->timestamp * 1000 : null, 'y' => (int) $p->price])->filter(fn($pt) => ! is_null($pt['x']))->values());

            function formatTime(ts) {
                const d = new Date(ts);
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const hh = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                return `${dd}/${mm} ${hh}:${mi}`;
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [
                        {
                            label: 'Giá của bạn',
                            data: points,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,.18)',
                            tension: 0.2,
                            fill: true,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                        },
                    ],
                },
                options: {
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
        })();
    </script>
@endsection
