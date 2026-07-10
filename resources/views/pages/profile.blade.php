@extends('layouts.app')
@section('title', 'My Profile')
@section('crumb', 'System')
@section('heading', 'My Profile')

@section('content')
<div class="cf-grid c2">
  <div class="cf-card">
    <h3>👤 My profile</h3>
    <div class="cf-kv">
      <div class="k">Name</div><div>{{ $user->name }}</div>
      <div class="k">Email</div><div class="cf-mono">{{ $user->email }}</div>
      <div class="k">Role</div><div style="text-transform:capitalize">{{ $user->role }}</div>
      <div class="k">Phone</div><div>{{ $user->phone ?: '—' }}</div>
      <div class="k">Member since</div><div>{{ $user->created_at->format('d M Y H:i') }}</div>
      <div class="k">Last sign-in</div><div>{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</div>
    </div>
  </div>

  <div class="cf-card">
    <h3>🔒 Change password</h3>

    @if ($errors->any())
      <div class="cf-lerr on" style="margin-bottom:12px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('profile.password') }}">
      @csrf @method('PUT')

      <div class="cf-fld">
        <label>Current password</label>
        <div class="cf-pwrap">
          <input class="cf-in" name="current_password" type="password">
          <button type="button" class="cf-eyebtn" title="Show / hide password">👁</button>
        </div>
      </div>

      <div class="cf-fld">
        <label>New password</label>
        <div class="cf-pwrap">
          <input class="cf-in" name="password" id="rgPass" type="password">
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

      <button class="cf-b p" type="submit" title="Save your new password">Update password</button>
      <div class="cf-note" style="margin-top:8px">
        Passwords are stored as bcrypt hashes on the server. Changing yours signs out any other session using the old one.
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
  <script src="{{ asset('js/auth.js') }}"></script>
@endpush
