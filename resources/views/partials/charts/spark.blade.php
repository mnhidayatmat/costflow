{{-- Six-month sparkline. $values list<float>, oldest first; last bar is "hot". --}}
@php($max = max(array_map('abs', $values)) ?: 1)

<div class="cf-spark">
  @foreach ($values as $i => $value)
    <i style="height:{{ max(6, abs($value) / $max * 100) }}%"
       @class(['hot' => $i === count($values) - 1])
       title="{{ money($value) }}"></i>
  @endforeach
</div>
