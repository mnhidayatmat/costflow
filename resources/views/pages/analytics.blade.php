@extends('layouts.app')
@section('title', 'Analytics')
@section('crumb', 'Overview')
@section('heading', 'Analytics')

@section('content')
<div class="cf-grid k4">
  <div class="cf-card cf-kpi" style="--kc:var(--ok)" title="Selling value of every approved job">
    <div class="lbl">Total win — all time</div>
    <div class="val">{{ money($allTime['win']) }}</div>
    <div class="sub">{{ $allTime['count'] }} approved jobs</div>
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--cy)" title="Total profit across all approved jobs">
    <div class="lbl">Total profit — all time</div>
    <div class="val">{{ money($allTime['profit']) }}</div>
    <div class="sub">{{ $allTime['count'] ? 'avg margin '.number_format($allTime['margin'], 1).'%' : '—' }}</div>
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--vi)" title="Department with the highest approved selling value">
    <div class="lbl">Top department</div>
    <div class="val" style="font-size:18px">{{ $topDepartment ?: '—' }}</div>
    <div class="sub">{{ $topDepartment ? money($topDepartmentValue) : '' }}</div>
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--am)" title="Share of submitted jobs that were returned">
    <div class="lbl">Return rate</div>
    <div class="val">{{ number_format($returnRate, 0) }}%</div>
    <div class="sub">Returned ÷ submitted</div>
  </div>
</div>

<div class="cf-grid c2">
  <div class="cf-card">
    <h3>🏭 Department performance (approved selling, RM)</h3>
    @include('partials.charts.bar', ['data' => $departments, 'empty' => 'No approved jobs yet.'])
  </div>
  <div class="cf-card">
    <h3>👔 Manager performance (approved profit, RM)</h3>
    @include('partials.charts.bar', ['data' => $managers, 'empty' => 'No approved jobs yet.', 'profit' => true, 'prefix' => '👔 '])
  </div>
</div>

<div class="cf-grid c2">
  <div class="cf-card">
    <h3>🧭 Status distribution</h3>
    @include('partials.charts.donut', ['counts' => $statusCounts])
  </div>

  <div class="cf-card">
    <h3>📅 Monthly wins — last 6 months</h3>
    @php($maxTrend = max(array_column($trend, 'value')) ?: 1)
    <div class="cf-trend">
      @foreach ($trend as $month)
        <div class="cf-tcol">
          <div class="cf-tbar" style="height:{{ max(3, $month['value'] / $maxTrend * 100) }}%" title="{{ money($month['value']) }}"></div>
          <div class="cf-tlab">{{ $month['label'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection
