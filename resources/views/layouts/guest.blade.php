<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Sign in') — COSTFLOW</title>
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="%230e7fc0"/><text x="32" y="44" font-size="36" font-weight="900" font-family="Segoe UI,Arial" fill="white" text-anchor="middle">C</text></svg>'>
<meta name="theme-color" content="#0a121e">
<link rel="stylesheet" href="{{ asset('css/costflow.css') }}">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('costflow_theme') || 'dark');</script>
</head>
<body class="cf-body">

<div class="cf-login" id="cfLogin">
  <div class="cf-lcard">
    <div class="cf-logo"><div class="cf-logomark">C</div><div class="cf-logotx">COST<b>FLOW</b></div></div>
    <div class="cf-lsub">BPE Energy Sdn. Bhd. — WCC cost estimation &amp; documentation platform</div>

    @sectionMissing('hideTabs')
      <div class="cf-ltabs">
        <a class="cf-ltab @if(request()->routeIs('login')) on @endif" href="{{ route('login') }}" title="Sign in with an existing account">Sign in</a>
        <a class="cf-ltab @if(request()->routeIs('register')) on @endif" href="{{ route('register') }}" title="Create a new BPE account">Create account</a>
      </div>
    @endif

    @if ($errors->any())
      <div class="cf-lerr on">{{ $errors->first() }}</div>
    @endif

    @if (session('status'))
      <div class="cf-lok on">{{ session('status') }}</div>
    @endif

    @yield('content')

    <div class="cf-lfoot">
      Access restricted to {{ '@'.config('costflow.email_domain') }} ·
      {{ config('costflow.max_login_attempts') }} wrong passwords locks the account for {{ config('costflow.lock_minutes') }} minutes
    </div>
  </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
</body>
</html>
