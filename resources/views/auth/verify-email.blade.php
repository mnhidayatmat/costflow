@extends('layouts.guest')
@section('title', 'Verify your email')
@section('hideTabs', true)

@section('content')
<div class="cf-fld">
  <label>Check your inbox</label>
  <p class="cf-note" style="font-size:12.5px;line-height:1.7">
    @if ($email)
      We emailed a verification link to <b>{{ $email }}</b>.
    @else
      We emailed you a verification link.
    @endif
    Click it to activate your account — it signs you straight in.
  </p>
</div>

<form method="POST" action="{{ route('verification.send') }}">
  @csrf
  @unless ($email)
    <div class="cf-fld">
      <label>Company email</label>
      <div class="cf-emailrow">
        <input class="cf-in" name="email" type="text" value="{{ old('email') }}" placeholder="name">
        <span class="cf-esuf">{{ '@'.config('costflow.email_domain') }}</span>
      </div>
    </div>
  @else
    <input type="hidden" name="email" value="{{ $email }}">
  @endunless

  <button class="cf-btn" type="submit"><span class="sp"></span>Resend verification email</button>
</form>

<div class="cf-altauth">
  <a href="{{ route('login') }}">← Back to sign in</a>
</div>
@endsection
