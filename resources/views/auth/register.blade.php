@extends('layouts.guest')
@section('title', 'Create account')

@section('content')
<form method="POST" action="{{ route('register') }}">
  @csrf

  <div class="cf-fld">
    <label>Full name</label>
    <input class="cf-in" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Nur Aisyah Binti Rahman">
  </div>

  <div class="cf-fld">
    <label>Role in BPE</label>
    <select class="cf-in" name="role" title="Your role decides your access level">
      <option value="">— Select your role —</option>
      @foreach (config('costflow.roles') as $role)
        <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role === 'it' ? 'IT' : $role) }}</option>
      @endforeach
    </select>
  </div>

  <div class="cf-fld">
    <label>Phone number (Malaysia)</label>
    <input class="cf-in" name="phone" type="tel" value="{{ old('phone') }}" placeholder="e.g. 012-3456789">
  </div>

  <div class="cf-fld">
    <label>Company email</label>
    <div class="cf-emailrow">
      <input class="cf-in" name="email" type="text" value="{{ old('email') }}" placeholder="name"
             title="Type just the name — {{ '@'.config('costflow.email_domain') }} is added automatically">
      <span class="cf-esuf">{{ '@'.config('costflow.email_domain') }}</span>
    </div>
  </div>

  <div class="cf-fld">
    <label>Password</label>
    <div class="cf-pwrap">
      <input class="cf-in" name="password" id="rgPass" type="password">
      <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
    </div>
    <div class="cf-pwreq" id="pwReq">
      <span data-req="len">✕ 8+ chars</span><span data-req="up">✕ Uppercase</span><span data-req="lo">✕ Lowercase</span><span data-req="num">✕ Number</span><span data-req="sym">✕ Symbol</span>
    </div>
  </div>

  <div class="cf-fld">
    <label>Confirm password</label>
    <div class="cf-pwrap">
      <input class="cf-in" name="password_confirmation" type="password">
      <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
    </div>
  </div>

  <button class="cf-btn" type="submit" title="Create your account"><span class="sp"></span>Create account</button>

  <div class="cf-lfoot">
    We'll email you a link to verify the address before you can sign in.<br>
    Your role sets your access: <b>Engineer</b> costs &amp; submits · <b>Management</b> approves · <b>IT</b> administers users &amp; audit.
  </div>
</form>
@endsection
