@extends('layouts.app')
@section('title', 'About')
@section('crumb', 'System')
@section('heading', 'About COSTFLOW')

@section('content')
<div class="cf-grid c2">
  <div class="cf-card">
    <h3>ℹ️ System information</h3>
    <div class="cf-kv">
      <div class="k">System Name</div><div>COSTFLOW</div>
      <div class="k">Version</div><div>{{ config('costflow.version') }}</div>
      <div class="k">Developed By</div><div>Mimi Nor Zalikha Binti Mohd Azmi</div>
      <div class="k">Institution</div><div>Universiti Malaysia Pahang Al-Sultan Abdullah (UMPSA)</div>
      <div class="k">Developed For</div><div>{{ config('costflow.company.name') }}</div>
      <div class="k">Year</div><div>2026</div>
      <div class="k">Stack</div><div>Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }} · {{ ucfirst(config('database.default')) }} · Brevo</div>
      <div class="k">Purpose</div><div>Digitalize the Excel-based WCC workflow: WCC1 planned budget, BPE Price quotation, WCC2 actual cost capture — centralized, standardized and auditable.</div>
    </div>
  </div>

  <div class="cf-card">
    <h3>🗓️ Project timeline</h3>
    <div class="cf-tl">
      <div class="it"><b>Phase 1 — Requirement study</b>Excel WCC workflow analysis with Encik Alfi &amp; Miss Ira Lee</div>
      <div class="it"><b>Phase 2 — WCC template digitalization</b>WCC1 / BPE Price / WCC2 with live calculation, dual currency, formulas</div>
      <div class="it"><b>Phase 3 — Spreadsheet UX</b>Column/row resizing, Excel-like undo-redo, formula engine, import/export</div>
      <div class="it"><b>Phase 4 — COSTFLOW platform</b>Authentication, roles, workflow, analytics &amp; audit</div>
      <div class="it"><b>Phase 5 — Production backend</b>Laravel with a real database, server-side roles, audit trail and Brevo email (this release)</div>
    </div>
  </div>
</div>
@endsection
