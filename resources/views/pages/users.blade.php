@extends('layouts.app')
@section('title', 'User Management')
@section('crumb', 'Governance')
@section('heading', 'User Management')

@section('content')
<div class="cf-card">
  <h3>👥 Users <span class="cf-note">— IT only · {{ '@'.config('costflow.email_domain') }} accounts</span></h3>

  <form method="POST" action="{{ route('users.store') }}" class="cf-filters" style="margin:6px 0 14px">
    @csrf
    <input class="cf-in" name="name" value="{{ old('name') }}" placeholder="Full name" style="width:200px">
    <input class="cf-in" name="email" value="{{ old('email') }}" placeholder="email{{ '@'.config('costflow.email_domain') }}" style="width:220px" title="Only {{ '@'.config('costflow.email_domain') }} addresses are accepted">
    <select class="cf-sel" name="role" title="Access level for the new user">
      @foreach (config('costflow.roles') as $role)
        <option value="{{ $role }}">{{ $role === 'it' ? 'IT' : ucfirst($role) }}</option>
      @endforeach
    </select>
    <input class="cf-in" name="password" type="text" placeholder="Temp password" style="width:160px" title="Temporary password, minimum 8 characters">
    <button class="cf-b p" type="submit" title="Create this user account">＋ Add user</button>
  </form>

  <table class="cf-t">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th style="width:260px">Actions</th></tr></thead>
    <tbody>
      @foreach ($users as $user)
        <tr>
          <td>{{ $user->name }}</td>
          <td class="cf-mono">{{ $user->email }}</td>
          <td>
            <form method="POST" action="{{ route('users.role', $user) }}" class="cf-inline-form">
              @csrf @method('PUT')
              <select class="cf-sel" name="role" title="Change this user's role" onchange="this.form.submit()">
                @foreach (config('costflow.roles') as $role)
                  <option value="{{ $role }}" @selected($user->role === $role)>{{ $role === 'it' ? 'IT' : ucfirst($role) }}</option>
                @endforeach
              </select>
            </form>
          </td>
          <td>
            @if ($user->isLocked())
              <span class="cf-st returned">Locked</span>
            @elseif (! $user->hasVerifiedEmail())
              <span class="cf-st submitted">Unverified</span>
            @else
              <span class="cf-st approved">Active</span>
            @endif
          </td>
          <td>
            <div class="cf-act">
              @if ($user->isLocked())
                <form method="POST" action="{{ route('users.unlock', $user) }}" class="cf-inline-form">
                  @csrf
                  <button class="cf-b" type="submit" title="Release the security lock now">Unlock</button>
                </form>
              @endif

              <form method="POST" action="{{ route('users.password', $user) }}" class="cf-inline-form">
                @csrf @method('PUT')
                <input type="hidden" name="password" value="">
                <button class="cf-b" type="submit" data-prompt="New temporary password for {{ $user->email }} (min 8 chars)"
                        data-prompt-field="password" title="Set a temporary password for this user">Reset password</button>
              </form>

              @if ($user->id !== auth()->id())
                <form method="POST" action="{{ route('users.destroy', $user) }}" class="cf-inline-form"
                      data-confirm="Remove user {{ $user->email }}? They will no longer be able to sign in.">
                  @csrf @method('DELETE')
                  <button class="cf-b warn gh" type="submit" title="Remove this account">✕</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
