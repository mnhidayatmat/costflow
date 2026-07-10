{{--
    Status distribution donut.
    $counts array<string,int> in pipeline order
--}}
@php
  $total = array_sum($counts) ?: 1;
  $radius = 52;
  $circumference = 2 * M_PI * $radius;
  $offset = 0;
@endphp

<div class="cf-donutwrap">
  <svg width="140" height="140" viewBox="0 0 140 140">
    @foreach ($counts as $status => $count)
      @php
        $length = $count / $total * $circumference;
      @endphp
      @if ($count > 0)
        <circle r="{{ $radius }}" cx="70" cy="70" fill="none"
                stroke="{{ status_color($status) }}" stroke-width="20"
                stroke-dasharray="{{ $length }} {{ $circumference - $length }}"
                stroke-dashoffset="{{ -$offset }}"
                transform="rotate(-90 70 70)"><title>{{ $status }}: {{ $count }}</title></circle>
      @endif
      @php($offset += $length)
    @endforeach
    <text x="70" y="66" text-anchor="middle" font-size="22" font-weight="800" fill="currentColor">{{ array_sum($counts) }}</text>
    <text x="70" y="84" text-anchor="middle" font-size="10" fill="currentColor" opacity=".55">WCCs</text>
  </svg>

  <div class="cf-leg">
    @foreach ($counts as $status => $count)
      <div class="li"><span class="dot" style="background:{{ status_color($status) }}"></span>{{ $status }} — <b style="color:var(--tx)">{{ $count }}</b></div>
    @endforeach
  </div>
</div>
