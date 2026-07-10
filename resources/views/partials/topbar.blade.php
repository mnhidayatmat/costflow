@php($user = auth()->user())
<header class="cf-top">
  <button class="cf-iconb" id="cfBurger" title="Open / close the menu">☰</button>
  <div>
    <div class="cf-crumb">COSTFLOW / <span>@yield('crumb', 'Overview')</span></div>
    <div class="cf-ptitle">@yield('heading', 'My Dashboard')</div>
  </div>
  <div class="cf-clock" title="Current date and time">
    <div class="t" id="cfClockT">—:—</div>
    <div class="d" id="cfClockD"></div>
  </div>
  <form class="cf-search" method="GET" action="{{ route('records.index') }}" title="Search records by quo no., client or title">
    <span class="ic">🔍</span>
    <input name="q" value="{{ request('q') }}" placeholder="Search WCC records… (Enter)">
  </form>
  <button class="cf-iconb" id="cfTheme" title="Switch between dark and light mode">☀️</button>
  <div class="cf-ava" title="{{ $user->name }} ({{ $user->role }})">{{ $user->initials() }}</div>
</header>
