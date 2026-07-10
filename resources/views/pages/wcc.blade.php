@extends('layouts.app')
@section('title', 'WCC Workspace')
@section('crumb', 'Costing')
@section('heading', 'WCC Workspace')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/wcc-template.css') }}">
@endpush

@section('content')
<div class="cf-wbar">
  <div class="cf-wname">
    🧾 <span id="wsName">{{ $record ? $record->quo_no.' — '.$record->client : 'Untitled WCC (not saved as record)' }}</span>
    <span class="cf-st {{ strtolower($record->status ?? 'Draft') }}" id="wsStatus">{{ $record->status ?? 'Draft' }}</span>
    @if ($readonly)
      <span class="cf-note" title="Approved records are frozen; submitted records are with management">🔒 Read-only</span>
    @endif
  </div>

  <div class="cf-wsp">
    <div class="cf-zoom" title="Zoom the WCC template (Ctrl + mouse wheel also works)">
      <button class="cf-zb" id="wsZoomOut" title="Zoom out">−</button>
      <span class="cf-zlbl" id="wsZoomLbl" title="Click to reset to A4 / 100%">100%</span>
      <button class="cf-zb" id="wsZoomIn" title="Zoom in">＋</button>
    </div>
    <div class="cf-pad" title="Scroll the template smoothly">
      <button class="cf-zb" id="wsL" title="Scroll left">◀</button><button class="cf-zb" id="wsU" title="Scroll up">▲</button>
      <button class="cf-zb" id="wsD" title="Scroll down">▼</button><button class="cf-zb" id="wsR" title="Scroll right">▶</button>
    </div>
    <button class="cf-b" id="wsMin" title="Hide / show the template panel">🗕 Minimize</button>
    <button class="cf-b" id="wsMax" title="Expand the template to full screen (Esc to exit)">⛶ Maximize</button>
    <a class="cf-b" href="{{ route('wcc.create') }}" title="Clear the template and start a blank WCC">＋ New</a>

    @unless ($readonly)
      <button class="cf-b p" id="wsSave" title="Save / update this WCC as a record in COSTFLOW">
        💾 {{ $record ? 'Update record' : 'Save as record' }}
      </button>
    @endunless

    <span class="cf-note">Template auto-saves locally as you type · saving writes the record to the server</span>
  </div>
</div>

<div class="cf-wccwrap">
  <div class="cf-tplhint">WCC TEMPLATE · double-click the tab bar for full screen</div>
  <div class="cf-tplfab">
    <button id="fabFit" title="Fit template to screen width">🖥</button>
    <button id="fabA4" title="A4 document size (100%)">📄</button>
    <button id="fabMax" title="Maximize / minimize — you can also double-click the tab bar">⛶</button>
  </div>

  @include('wcc.template')
</div>
@endsection

@push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script>
    /* Handed to wcc-workspace.js: where to save, and what to restore. */
    window.COSTFLOW_WCC = {
      recordId: @json($record?->id),
      saveUrl: @json($record ? route('wcc.update', $record) : route('wcc.store')),
      saveMethod: @json($record ? 'PUT' : 'POST'),
      attachmentUrl: @json(route('wcc.attachments.store')),
      version: @json($record?->version ?? 0),
      readonly: @json($readonly),
      snapshot: @json($snapshot),
    };

    /*
     * The engine keeps an as-you-type draft in one fixed localStorage key and
     * rehydrates from it the moment it loads. That buffer belongs to whichever
     * sheet was open last — so if this page is a different sheet, drop it now,
     * before the engine can restore the wrong record's numbers.
     *
     * Same sheet as last time: the buffer survives, and a refresh keeps your
     * unsaved edits.
     */
    (function () {
      var OWNER_KEY = 'costflow_wcc_owner';
      var DRAFT_KEY = 'costflow_wcc_state_v7';
      var owner = String(window.COSTFLOW_WCC.recordId || 'new');

      if (localStorage.getItem(OWNER_KEY) !== owner) {
        localStorage.removeItem(DRAFT_KEY);
        localStorage.setItem(OWNER_KEY, owner);
      }

      /*
       * A surviving draft is newer than anything the server has — it is exactly
       * the edits made since the last Save. Let it win, and skip the snapshot
       * restore. On a fresh open there is no draft, so the server wins.
       */
      window.COSTFLOW_WCC.hasLocalDraft = localStorage.getItem(DRAFT_KEY) !== null;
    })();
  </script>
  <script src="{{ asset('js/wcc-engine.js') }}"></script>
  <script src="{{ asset('js/wcc-workspace.js') }}"></script>
@endpush
