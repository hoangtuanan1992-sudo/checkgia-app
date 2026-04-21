@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1200px">
        <div class="card-header">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <h1 class="card-title">Lịch sử giá: {{ $product->name }}</h1>
                    <p class="card-sub">So sánh biến động giá trong {{ $days }} ngày qua</p>
                </div>
                <div style="display:flex;gap:10px">
                    <a href="{{ route('shopee.dashboard') }}" class="btn btn-secondary">Quay lại</a>
                    <form method="GET" style="display:flex;gap:5px">
                        <select name="days" class="input" onchange="this.form.submit()" style="padding:6px 12px">
                            <option value="3" {{ $days == 3 ? 'selected' : '' }}>3 ngày</option>
                            <option value="7" {{ $days == 7 ? 'selected' : '' }}>7 ngày</option>
                            <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 ngày</option>
                            <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 ngày</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="price-chart" style="width:100%;height:450px"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartData = @json($chartData);
            
            const options = {
                series: chartData.map(s => ({
                    name: s.name,
                    data: s.data.map(d => ({
                        x: new Date(d.t).getTime(),
                        y: d.y
                    }))
                })),
                chart: {
                    type: 'line',
                    height: 450,
                    zoom: {
                        enabled: true
                    },
                    animations: {
                        enabled: false
                    },
                    toolbar: {
                        show: true
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                xaxis: {
                    type: 'datetime',
                    labels: {
                        datetimeUTC: false,
                        format: 'dd/MM HH:mm'
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                        }
                    }
                },
                tooltip: {
                    x: {
                        format: 'dd/MM/yyyy HH:mm'
                    },
                    y: {
                        formatter: function (value) {
                            return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                        }
                    }
                },
                colors: ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6610f2'],
                legend: {
                    position: 'top'
                }
            };

            const chart = new ApexCharts(document.querySelector("#price-chart"), options);
            chart.render();
        });
    </script>
@endsection
