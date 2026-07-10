@extends('layouts.guest')
@section('title', 'Sign in with a code')
@section('hideTabs', true)

@section('content')
@php($email = session('otp_email') ?? old('email'))

@if ($email)
  {{-- Step 2: the code has been sent, ask for it. --}}
  <form method="POST" action="{{ route('otp.verify') }}">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">

    <div class="cf-fld">
      <label>Enter the {{ config('costflow.otp.length') }}-digit code sent to {{ $email }}</label>
      <input class="cf-in cf-otpbox" name="code" inputmode="numeric" autocomplete="one-time-code"
             maxlength="{{ config('costflow.otp.length') }}" autofocus placeholder="000000">
    </div>

    <button class="cf-btn" type="submit"><span class="sp"></span>Sign in</button>
  </form>

  <form method="POST" action="{{ route('otp.send') }}" style="margin-top:10px">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <div class="cf-altauth">
      <button type="submit" style="background:none;border:none;color:var(--tx2);font-weight:700;cursor:pointer;font-size:11.5px">↻ Send me a new code</button>
      <a href="{{ route('login') }}">← Back to password sign-in</a>
    </div>
  </form>
@else
  {{-- Step 1: which address? --}}
  <form method="POST" action="{{ route('otp.send') }}">
    @csrf

    <div class="cf-fld">
      <label>Company email</label>
      <div class="cf-emailrow">
        <input class="cf-in" name="email" type="text" value="{{ old('email') }}" placeholder="name" autofocus>
        <span class="cf-esuf">{{ '@'.config('costflow.email_domain') }}</span>
      </div>
    </div>

    <button class="cf-btn" type="submit"><span class="sp"></span>Email me a sign-in code</button>
  </form>

  <div class="cf-altauth">
    <a href="{{ route('login') }}">← Back to password sign-in</a>
  </div>
@endif

<div class="cf-lfoot" style="margin-top:14px">
  The code expires in {{ config('costflow.otp.ttl_minutes') }} minutes. The email also carries a one-click sign-in link.
</div>
@endsection
