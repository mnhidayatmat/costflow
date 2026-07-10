@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
<form method="POST" action="{{ route('login') }}">
  @csrf

  <div class="cf-fld">
    <label>Company email</label>
    <div class="cf-emailrow">
      <input class="cf-in" name="email" type="text" value="{{ old('email') }}" placeholder="name"
             autocomplete="username" autofocus
             title="Type just the name — {{ '@'.config('costflow.email_domain') }} is added automatically">
      <span class="cf-esuf">{{ '@'.config('costflow.email_domain') }}</span>
    </div>
  </div>

  <div class="cf-fld">
    <label>Password</label>
    <div class="cf-pwrap">
      <input class="cf-in" name="password" type="password" placeholder="••••••••" autocomplete="current-password">
      <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
    </div>
  </div>

  <button class="cf-btn" type="submit" title="Sign in to COSTFLOW"><span class="sp"></span>Sign in</button>
</form>

<div class="cf-altauth">
  <a href="{{ route('otp.create') }}">✉️ Email me a sign-in code instead</a>
  <a href="{{ route('password.request') }}">Forgot your password?</a>
</div>

@if (app()->environment('local'))
  <div class="cf-demo">
    <b>Demo accounts</b> (password: <b>Costflow@123</b>)<br>
    admin (IT) · isnari (Management) · alfi (Engineer) — e.g. type <b>admin</b> and the suffix is added for you
  </div>
@endif
@endsection
