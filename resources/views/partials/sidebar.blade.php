@php($user = auth()->user())
<aside class="cf-side" id="cfSide">
  <div class="cf-logo"><div class="cf-logomark">C</div><div class="cf-logotx">COST<b>FLOW</b></div></div>

  <div class="cf-navsec">Overview</div>
  <ul class="cf-nav">
    <li><a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')]) title="KPIs, pipeline and performance at a glance"><span class="ic">🏠</span>Dashboard</a></li>
    <li><a href="{{ route('analytics') }}" @class(['on' => request()->routeIs('analytics')]) title="All-time wins, margins and trends"><span class="ic">📈</span>Analytics</a></li>
  </ul>

  <div class="cf-navsec">Costing</div>
  <ul class="cf-nav">
    @can('create', App\Models\WccRecord::class)
      <li><a href="{{ route('wcc.create') }}" @class(['on' => request()->routeIs('wcc.*')]) title="Open the WCC1 / BPE Price / WCC2 template"><span class="ic">🧾</span>WCC Workspace</a></li>
    @endcan
    <li><a href="{{ route('records.index') }}" @class(['on' => request()->routeIs('records.*')]) title="All saved WCCs and their workflow status"><span class="ic">🗂️</span>WCC Records <span class="cf-badge">{{ $recordCount }}</span></a></li>
  </ul>

  <div class="cf-navsec">Governance</div>
  <ul class="cf-nav">
    <li><a href="{{ route('audit.index') }}" @class(['on' => request()->routeIs('audit.*')]) title="Every action in the system, tracked"><span class="ic">🕓</span>Audit Log</a></li>
    @can('manage-users')
      <li><a href="{{ route('users.index') }}" @class(['on' => request()->routeIs('users.*')]) title="Add users, change roles, unlock accounts (IT)"><span class="ic">👥</span>User Management</a></li>
    @endcan
  </ul>

  <div class="cf-navsec">System</div>
  <ul class="cf-nav">
    <li><a href="{{ route('about') }}" @class(['on' => request()->routeIs('about')]) title="System information and project timeline"><span class="ic">ℹ️</span>About / Timeline</a></li>
    <li><a href="{{ route('profile.edit') }}" @class(['on' => request()->routeIs('profile.*')]) title="Your account details and password"><span class="ic">👤</span>Profile</a></li>
  </ul>

  <div class="cf-sfoot">
    <div class="cf-me">
      <div class="cf-ava">{{ $user->initials() }}</div>
      <div><div class="nm">{{ $user->name }}</div><div class="rl">{{ $user->role }}</div></div>
    </div>
    <ul class="cf-nav">
      <li>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" title="Sign out of COSTFLOW"><span class="ic">⏻</span>Logout</a>
        </form>
      </li>
    </ul>
    <div class="cf-note" style="padding:6px 12px 0">COSTFLOW v{{ config('costflow.version') }} · UMPSA × BPE Energy</div>
  </div>
</aside>
