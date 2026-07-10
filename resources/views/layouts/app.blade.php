<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'COSTFLOW') — BPE Energy Cost Estimation Platform</title>
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="%230e7fc0"/><text x="32" y="44" font-size="36" font-weight="900" font-family="Segoe UI,Arial" fill="white" text-anchor="middle">C</text></svg>'>
<meta name="theme-color" content="#0a121e">
<link rel="stylesheet" href="{{ asset('css/costflow.css') }}">
{{-- Applied before first paint so the dark theme never flashes white. --}}
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('costflow_theme') || 'dark');</script>
@stack('styles')
</head>
<body class="cf-body">

<div class="cf-app" id="cfApp">
  @include('partials.sidebar')

  <div class="cf-main">
    @include('partials.topbar')

    <main class="cf-content">
      @yield('content')

      <footer class="cf-wm">
        <div class="mk"><i>C</i>COSTFLOW <span style="font-weight:600;letter-spacing:0">v{{ config('costflow.version') }}</span></div>
        <div>Designed &amp; developed by <b>Mimi Nor Zalikha Binti Mohd Azmi</b> · Universiti Malaysia Pahang Al-Sultan Abdullah (UMPSA)</div>
        <div>Built for <b>BPE Energy Sdn. Bhd.</b> · Final Year Project · © 2026</div>
      </footer>
    </main>
  </div>
</div>

<div class="cf-mo" id="cfMo"><div class="cf-mcard" id="cfMoCard"></div></div>
<div class="cf-toast" id="cfToast"></div>

{{-- Server-side flashes are handed to the client toast. --}}
<script>
  window.COSTFLOW = {
    csrf: @json(csrf_token()),
    idleMinutes: @json((int) config('costflow.idle_minutes')),
    logoutUrl: @json(route('logout')),
    flash: @json(session('status')),
    errors: @json($errors->all()),
  };
</script>
<script src="{{ asset('js/costflow.js') }}"></script>
@stack('scripts')
</body>
</html>
