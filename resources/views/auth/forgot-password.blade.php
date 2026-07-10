@extends('layouts.guest')
@section('title', 'Forgot password')
@section('hideTabs', true)

@section('content')
<div class="cf-fld">
  <p class="cf-note" style="font-size:12.5px;line-height:1.7">
    Enter your company email and we'll send you a link to choose a new password.
  </p>
</div>

<form method="POST" action="{{ route('password.email') }}">
  @csrf
  <div class="cf-fld">
    <label>Company email</label>
    <div class="cf-emailrow">
      <input class="cf-in" name="email" type="text" value="{{ old('email') }}" placeholder="name" autofocus>
      <span class="cf-esuf">{{ '@'.config('costflow.email_domain') }}</span>
    </div>
  </div>

  <button class="cf-btn" type="submit"><span class="sp"></span>Email password reset link</button>
</form>

<div class="cf-altauth">
  <a href="{{ route('otp.create') }}">✉️ Or sign in with a one-time code</a>
  <a href="{{ route('login') }}">← Back to sign in</a>
</div>
@endsection
