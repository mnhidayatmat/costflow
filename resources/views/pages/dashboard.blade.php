@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Overview')
@section('heading', 'My Dashboard')

@section('content')
@php
  $user = auth()->user();
  $hour = now()->hour;
  $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
  $dWin = delta_chip($current['win'], $previous['win']);
  $dProfit = delta_chip($current['profit'], $previous['profit']);
@endphp

<div class="cf-hero">
  <div class="cf-hl">
    <div class="cf-hgreet">{{ $greeting }} ·</div>
    <h1 class="cf-hh">{{ $user->name }}</h1>
    <p class="cf-hs">Here's where your costing pipeline stands today — jump into the workspace to cost, quote and close.</p>
    <div class="cf-chips">
      @can('create', App\Models\WccRecord::class)
        <a class="cf-chip" href="{{ route('wcc.create') }}" title="Open the WCC template workspace">🧾 Open WCC Workspace</a>
      @endcan
      <a class="cf-chip" href="{{ route('records.index') }}" title="Browse all saved WCC records">🗂️ Review records</a>
      <a class="cf-chip" href="{{ route('analytics') }}" title="See company-wide performance">📈 Performance</a>
    </div>
  </div>
  <div class="cf-hstats">
    <div class="cf-hstat" title="Total WCC records in the system"><div class="v">{{ $headline['total'] }}</div><div class="l">WCC records</div></div>
    <div class="cf-hstat" title="Records approved by management"><div class="v">{{ $headline['approved'] }}</div><div class="l">Approved</div></div>
    <div class="cf-hstat" title="Submitted records awaiting a decision"><div class="v">{{ $headline['awaiting'] }}</div><div class="l">Awaiting review</div></div>
  </div>
</div>

<div class="cf-pipe">
  @foreach ($statusCounts as $status => $count)
    <a class="cf-stage" href="{{ route('records.index', ['status' => $status]) }}" title="Click to view all {{ $status }} records">
      <div class="n">{{ $count }}</div>
      <div class="l"><span class="dot" style="background:{{ status_color($status) }}"></span>{{ $status }}</div>
    </a>
  @endforeach
</div>

<div class="cf-grid k4">
  <div class="cf-card cf-kpi" style="--kc:var(--ok)" title="Selling value of jobs approved this month">
    <div class="lbl">Total win — this month</div>
    <div class="val">{{ money($current['win']) }}</div>
    <div class="sub"><span class="cf-delta {{ $dWin['class'] }}">{{ $dWin['label'] }}</span> vs last month</div>
    @include('partials.charts.spark', ['values' => $winSpark])
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--cy)" title="Profit (selling − cost) on jobs approved this month">
    <div class="lbl">Profit — this month</div>
    <div class="val">{{ money($current['profit']) }}</div>
    <div class="sub">
      <span class="cf-delta {{ $dProfit['class'] }}">{{ $dProfit['label'] }}</span>
      <span>{{ $current['count'] ? 'avg margin '.number_format($current['margin'], 1).'% · '.$current['count'].' jobs' : 'no approved jobs this month' }}</span>
    </div>
    @include('partials.charts.spark', ['values' => $profitSpark])
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--vi)" title="WCCs still moving through costing and review">
    <div class="lbl">Active WCC1</div>
    <div class="val">{{ $headline['active'] }}</div>
    <div class="sub">Draft · Costed · Submitted</div>
  </div>

  <div class="cf-card cf-kpi" style="--kc:var(--am)" title="Approved jobs still waiting for actual costs (WCC2)">
    <div class="lbl">Pending WCC2</div>
    <div class="val">{{ $headline['pending_wcc2'] }}</div>
    <div class="sub">Approved, awaiting actual costs</div>
  </div>
</div>

<div class="cf-grid c2">
  <div class="cf-card">
    <h3>🏭 Department performance <span class="cf-note">(approved selling, RM)</span></h3>
    @include('partials.charts.bar', ['data' => $departments, 'empty' => 'No approved jobs yet.'])
  </div>
  <div class="cf-card">
    <h3>👔 Manager performance <span class="cf-note">(approved profit, RM)</span></h3>
    @include('partials.charts.bar', ['data' => $managers, 'empty' => 'No approved jobs yet — profit per manager will appear here.', 'profit' => true, 'prefix' => '👔 '])
  </div>
</div>

<div class="cf-grid c2">
  <div class="cf-card">
    <h3>🧭 WCC status overview</h3>
    @include('partials.charts.donut', ['counts' => $statusCounts])
  </div>
  <div class="cf-card">
    <h3>⚡ Quick actions</h3>
    <div class="cf-qa">
      @can('create', App\Models\WccRecord::class)
        <a class="cf-qab" href="{{ route('wcc.create') }}" title="Start costing a new job"><div class="t">🧾 New WCC</div><div class="s">Start costing in the workspace</div></a>
      @endcan
      <a class="cf-qab" href="{{ route('records.index') }}" title="Open, submit or approve records"><div class="t">🗂️ Records</div><div class="s">Open, submit or approve</div></a>
      <a class="cf-qab" href="{{ route('analytics') }}" title="Company performance overview"><div class="t">📈 Analytics</div><div class="s">Wins, margins &amp; trends</div></a>
      <a class="cf-qab" href="{{ route('audit.index') }}" title="Full history of system activity"><div class="t">🕓 Audit log</div><div class="s">Every action, tracked</div></a>
    </div>
  </div>
</div>

<div class="cf-card cf-audit">
  <h3>🕓 Recent activity</h3>
  <table class="cf-t">
    <thead><tr><th style="width:150px">When</th><th style="width:170px">Who</th><th>Action</th></tr></thead>
    <tbody>
      @forelse ($recentActivity as $entry)
        <tr>
          <td class="cf-note">{{ $entry->created_at?->format('d M Y H:i') }}</td>
          <td class="cf-mono">{{ $entry->actor }}</td>
          <td><b>{{ $entry->action }}</b> <span class="cf-note">{{ $entry->detail }}</span></td>
        </tr>
      @empty
        <tr><td colspan="3" class="cf-empty">No activity yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
