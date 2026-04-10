@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Báo cáo tổng quan</h1>
                    <p class="card-sub">Tổng hợp trong 7 ngày gần nhất</p>
                </div>
                <div style="display:flex;gap:8px">
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                        <div class="card-body" style="padding:16px">
                            <div class="hint" style="margin-top:0">Biến động giá của bạn (7 ngày)</div>
                            <div style="font-size:26px;font-weight:800;margin-top:6px">{{ number_format($ownChangesCount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                        <div class="card-body" style="padding:16px">
                            <div class="hint" style="margin-top:0">Biến động giá đối thủ (7 ngày)</div>
                            <div style="font-size:26px;font-weight:800;margin-top:6px">{{ number_format($competitorChangesCount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                        <div class="card-body" style="padding:16px">
                            <div class="hint" style="margin-top:0">Tổng biến động (7 ngày)</div>
                            <div style="font-size:26px;font-weight:800;margin-top:6px">{{ number_format($ownChangesCount + $competitorChangesCount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px;align-items:start">
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Top sản phẩm bị đối thủ rẻ hơn</h2>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            @if($topProductsCheaper->isEmpty())
                                <div class="hint">Chưa có dữ liệu.</div>
                            @else
                                <div class="table-wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Sản phẩm</th>
                                                <th>Đối thủ rẻ nhất</th>
                                                <th style="text-align:right">Chênh</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($topProductsCheaper as $row)
                                                <tr>
                                                    <td style="font-weight:600">{{ $row['product']->name }}</td>
                                                    <td>
                                                        <div style="display:flex;flex-direction:column;gap:2px">
                                                            <div style="font-weight:600">{{ $row['best_competitor']->competitorSite?->name ?? $row['best_competitor']->name }}</div>
                                                            <div class="hint" style="margin-top:0">{{ number_format($row['best_price'], 0, ',', '.') }}đ</div>
                                                        </div>
                                                    </td>
                                                    <td style="text-align:right;color:var(--danger);font-weight:700">-{{ number_format($row['diff'], 0, ',', '.') }}đ</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Top đối thủ hay rẻ hơn</h2>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($topCompetitorsOftenCheaper->isEmpty())
                                    <div class="hint">Chưa có dữ liệu.</div>
                                @else
                                    <div class="table-wrap">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Đối thủ</th>
                                                    <th style="text-align:right">Số sản phẩm</th>
                                                    <th style="text-align:right">Chênh TB</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($topCompetitorsOftenCheaper as $row)
                                                    <tr>
                                                        <td style="font-weight:600">{{ $row['site_name'] }}</td>
                                                        <td style="text-align:right">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                                        <td style="text-align:right;color:var(--danger);font-weight:700">-{{ number_format($row['avg_diff'], 0, ',', '.') }}đ</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Sản phẩm biến động nhiều nhất (giá của bạn)</h2>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($topProductsMostChanges->isEmpty())
                                    <div class="hint">Chưa có dữ liệu.</div>
                                @else
                                    <div class="table-wrap">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Sản phẩm</th>
                                                    <th style="text-align:right">Số lần</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($topProductsMostChanges as $row)
                                                    <tr>
                                                        <td style="font-weight:600">{{ $row['product']->name }}</td>
                                                        <td style="text-align:right">{{ number_format($row['changes'], 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
