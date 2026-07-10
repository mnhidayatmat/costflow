{{--
    Horizontal bar chart.
    $data  Collection<string, float> — already sorted, biggest first
    $empty Message when there is nothing to plot
    $profit Style the fill as a profit bar (used by the manager chart)
--}}
@php
  $max = $data->map(fn ($v) => abs($v))->max() ?: 1;
  $prefix = $prefix ?? '';
@endphp

<div class="cf-bar">
  @forelse ($data as $label => $value)
    <div class="cf-brow" title="{{ $label }}: {{ money($value) }}">
      <div class="nm">{{ $prefix }}{{ $label }}</div>
      <div class="cf-btrk">
        <div class="cf-bfill {{ ($profit ?? false) ? 'mg' : '' }}" style="width:{{ max(3, abs($value) / $max * 100) }}%"></div>
      </div>
      <div class="vv">{{ money($value) }}</div>
    </div>
  @empty
    <div class="cf-empty">{{ $empty }}</div>
  @endforelse
</div>
