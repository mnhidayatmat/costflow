@php
  $company = config('costflow.company');
  // Drop the company logo at public/img/bpe-logo.png and it appears on every
  // WCC1 / BPE Price / WCC2 document. Absent, the letterhead is text-only.
  $logo = file_exists(public_path('img/bpe-logo.png'));
@endphp
<div class="wcc-hd">
  <div class="wcc-logo">
    @if ($logo)
      <img src="{{ asset('img/bpe-logo.png') }}" alt="{{ $company['name'] }}">
    @endif
  </div>
  <div class="wcc-ci">
    <div class="wcc-cn">{{ $company['name'] }}</div>
    <div class="wcc-ca">{{ $company['address'] }}<br>Tel: {{ $company['tel'] }} | {{ $company['email'] }} | SST: {{ $company['sst'] }}</div>
  </div>
</div>
