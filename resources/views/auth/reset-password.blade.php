@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('hideTabs', true)

@section('content')
<form method="POST" action="{{ route('password.update') }}">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">

  <div class="cf-fld">
    <label>Company email</label>
    <input class="cf-in" name="email" type="email" value="{{ old('email', $email) }}" readonly>
  </div>

  <div class="cf-fld">
    <label>New password</label>
    <div class="cf-pwrap">
      <input class="cf-in" name="password" id="rgPass" type="password" autofocus>
      <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
    </div>
    <div class="cf-pwreq" id="pwReq">
      <span data-req="len">✕ 8+ chars</span><span data-req="up">✕ Uppercase</span><span data-req="lo">✕ Lowercase</span><span data-req="num">✕ Number</span><span data-req="sym">✕ Symbol</span>
    </div>
  </div>

  <div class="cf-fld">
    <label>Confirm new password</label>
    <div class="cf-pwrap">
      <input class="cf-in" name="password_confirmation" type="password">
      <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
    </div>
  </div>

  <button class="cf-btn" type="submit"><span class="sp"></span>Update password</button>
</form>
@endsection
