@extends('layouts.app')
@section('title', 'Audit Log')
@section('crumb', 'Governance')
@section('heading', 'Audit Log')

@section('content')
<div class="cf-card cf-audit">
  <div class="cf-filters" style="margin-bottom:12px">
    <form method="GET" action="{{ route('audit.index') }}" style="display:contents">
      <input class="cf-in" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Filter audit log…" style="width:260px" title="Type to filter audit entries">
      <button class="cf-b" type="submit">Filter</button>
    </form>

    @can('clear-audit-log')
      <form method="POST" action="{{ route('audit.destroy') }}" class="cf-inline-form"
            data-confirm="Clear the audit log? IT-only. All entries will be removed.">
        @csrf @method('DELETE')
        <button class="cf-b warn" type="submit" title="Erase the entire audit log (IT only)">Clear log</button>
      </form>
    @endcan
  </div>

  <table class="cf-t">
    <thead><tr><th style="width:170px">When</th><th style="width:220px">Who</th><th style="width:170px">Action</th><th>Detail</th></tr></thead>
    <tbody>
      @forelse ($logs as $log)
        <tr>
          <td class="cf-note">{{ $log->created_at?->format('d M Y H:i') }}</td>
          <td class="cf-mono">{{ $log->actor }}</td>
          <td><b>{{ $log->action }}</b></td>
          <td class="cf-note">{{ $log->detail }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="cf-empty">No audit entries.</td></tr>
      @endforelse
    </tbody>
  </table>

  {{ $logs->links('partials.pagination') }}
</div>
@endsection
