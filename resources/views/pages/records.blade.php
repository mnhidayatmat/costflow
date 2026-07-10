@extends('layouts.app')
@section('title', 'WCC Records')
@section('crumb', 'Costing')
@section('heading', 'WCC Records')

@section('content')
<div class="cf-card">
  <form class="cf-filters" method="GET" action="{{ route('records.index') }}" style="margin-bottom:14px">
    <input class="cf-in" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Filter by quo no. / client / title…" style="width:260px" title="Type to filter the record list">

    <select class="cf-sel" name="status" title="Filter by workflow status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      @foreach (config('costflow.statuses') as $status)
        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
      @endforeach
    </select>

    <select class="cf-sel" name="dept" title="Filter by department" onchange="this.form.submit()">
      <option value="">All departments</option>
      @foreach (config('costflow.departments') as $dept)
        <option value="{{ $dept }}" @selected(($filters['dept'] ?? '') === $dept)>{{ $dept }}</option>
      @endforeach
    </select>

    <button class="cf-b" type="submit">Filter</button>
    <span class="cf-note">{{ $records->total() }} of {{ $total }} records</span>

    @can('create', App\Models\WccRecord::class)
      <a class="cf-b p" href="{{ route('wcc.create') }}" style="margin-left:auto" title="Open the workspace to cost a new job">＋ New WCC in Workspace</a>
    @endcan
  </form>

  <div style="overflow:auto">
    <table class="cf-t">
      <thead>
        <tr>
          <th>Quo No.</th><th>Client</th><th>Project</th><th>Dept</th><th>Manager</th>
          <th>Planned cost</th><th>Selling</th><th>Actual</th><th>Margin</th>
          <th>Status</th><th>Updated</th><th style="width:250px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($records as $record)
          <tr>
            <td class="cf-mono">{{ $record->quo_no }}</td>
            <td>{{ $record->client }}</td>
            <td>{{ $record->title }}</td>
            <td>{{ $record->dept ?: '—' }}</td>
            <td>{{ $record->manager ?: '—' }}</td>
            <td>{{ money($record->planned_cost) }}</td>
            <td>{{ money($record->selling) }}</td>
            <td>{{ $record->actual > 0 ? money($record->actual) : '—' }}</td>
            <td>{{ $record->selling > 0 ? number_format($record->marginPercent(), 1).'%' : '—' }}</td>
            <td><span class="cf-st {{ strtolower($record->status) }}">{{ $record->status }}</span></td>
            <td class="cf-note">{{ $record->updated_at->format('d M Y H:i') }}</td>
            <td>
              <div class="cf-act">
                <a class="cf-b" href="{{ route('wcc.open', $record) }}" title="Load this WCC into the workspace">Open</a>

                @foreach (App\Models\WccRecord::TRANSITIONS[$record->status] as $next)
                  @can('transition', [$record, $next])
                    <form method="POST" action="{{ route('records.transition', $record) }}" class="cf-inline-form">
                      @csrf
                      <input type="hidden" name="to" value="{{ $next }}">
                      <button class="cf-b {{ $next === 'Approved' ? 'p' : ($next === 'Returned' ? 'warn' : '') }}"
                              type="submit"
                              @if ($next === 'Returned') data-prompt="Reason for returning {{ $record->quo_no }}?" @endif
                              title="{{ match ($next) {
                                  'Costed' => 'Mark costing as complete',
                                  'Submitted' => 'Send to management for approval',
                                  'Approved' => 'Approve this WCC (Management)',
                                  'Returned' => 'Return for rework (Management)',
                                  default => $next,
                              } }}">
                        {{ match ($next) {
                            'Costed' => 'Mark costed',
                            'Submitted' => 'Submit',
                            'Approved' => 'Approve',
                            'Returned' => 'Return',
                            default => $next,
                        } }}
                      </button>
                    </form>
                  @endcan
                @endforeach

                @can('delete', $record)
                  <form method="POST" action="{{ route('records.destroy', $record) }}" class="cf-inline-form"
                        data-confirm="Delete {{ $record->quo_no }}? This permanently removes the record.">
                    @csrf @method('DELETE')
                    <button class="cf-b warn gh" type="submit" title="Delete this record permanently (IT)">✕</button>
                  </form>
                @endcan
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="12" class="cf-empty">No records match.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $records->links('partials.pagination') }}
</div>
@endsection
