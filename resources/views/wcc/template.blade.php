{{--
    The WCC1 / BPE Price / WCC2 spreadsheet, lifted verbatim from the approved
    design. The engine in public/js/wcc-engine.js drives it by element id, so
    ids and the DOM shape here are load-bearing — do not rename or reorder.
--}}
<div class="wcc-shell" id="wcc-root">

<div class="wcc-tabbar">
  <div class="wcc-tab on" data-t="w1">WCC1</div>
  <div class="wcc-tab" data-t="bpe">BPE Price</div>
  <div class="wcc-tab" data-t="w2">WCC2</div>
  <div class="wcc-tab g" data-t="mgr">Manager</div>
  <div class="wcc-tab" data-t="rates">Rates</div>
  <div class="wcc-ur">
    <button class="wcc-ub off" id="ub-u" onclick="doUndo()" title="Undo (Ctrl+Z)">↶ Undo</button>
    <button class="wcc-ub off" id="ub-r" onclick="doRedo()" title="Redo (Ctrl+Y)">↷ Redo</button>
    <span id="saveStat" style="font-size:9px;color:#1a7a3c;align-self:center;margin-left:6px" title="Your data is saved in this browser and reloads automatically next time you open this file">💾 auto-saved</span>
    <button class="wcc-ub" id="ub-clear" onclick="clearSaved()" title="Erase the saved data in this browser and start a blank template">Clear saved</button>
    <button class="wcc-ub" id="ub-import" onclick="importXlsx()" title="Import a filled COSTFLOW Excel template (Settings + WCC1) into the system" style="background:#1b6b3a;color:#fff;border-color:#1b6b3a">⬆ Import Excel</button>
  </div>
</div>

<div class="wcc-pane on" data-p="w1">
<div class="wcc-scroll"><div class="wcc-doc">
  @include('wcc.letterhead')
  <div class="wcc-dt">WORK COMPLETION CERTIFICATE (WCC1) — PLANNED BUDGET</div>
  <div style="background:#fff">
    <div class="wcc-jg"><div class="wcc-jl">Date</div><input class="wcc-ji wcc-date" id="w1-date" type="date" oninput="calc();rs()" onclick="dpick(this)" ondblclick="dpick(this)" onfocus="dpick(this)" title="Click anywhere in the box to open the calendar"></div>
    <div class="wcc-jg"><div class="wcc-jl">Quo No.</div><input class="wcc-ji" id="w1-quo" oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Contract No.</div>
      <select class="wcc-ji" id="w1-contract" style="background:var(--yellow)" onchange="rs()">
        <option value="">— Select contract —</option>
        <option>PMA/5000/200/2026 (HB)</option>
        <option>SKO/3000/100/2026 (UPS)</option>
        <option value="__other">Please add (type below)</option>
      </select>
    </div>
    <div class="wcc-jg" id="w1-contract-other-row" style="display:none"><div class="wcc-jl">New contract no.</div><input class="wcc-ji" id="w1-contract-other" placeholder="Type contract no." oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Client Name</div><input class="wcc-ji" id="w1-client" oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Project Title</div><input class="wcc-ji" id="w1-desc" oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Manager</div>
      <select class="wcc-ji" id="w1-mgr" style="background:var(--yellow)" onchange="rs()">
        <option value="">— Select manager —</option>
        <option>ALFI</option><option>OMAR</option><option>AZWAN</option><option>IKHWAN</option>
        <option>ZAFUAN</option><option>AZIZI</option><option>SHAHIR</option><option>IRA LEE</option>
        <option value="__other">Please add (type below)</option>
      </select>
    </div>
    <div class="wcc-jg" id="w1-mgr-other-row" style="display:none"><div class="wcc-jl">New manager name</div><input class="wcc-ji" id="w1-mgr-other" placeholder="Type manager short name" oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Department</div>
      <select class="wcc-ji" id="w1-dept" style="background:var(--yellow)" onchange="rs()">
        <option value="">— Select department —</option>
        <option>Electrical</option><option>Instrument</option><option>CCVT</option>
        <option>NWK 99</option><option>UPS &amp; Battery</option><option>Subsea Cable</option>
        <option>Diesel Generator</option><option>Heater Bundle</option><option value="__other">Please add (type below)</option>
      </select>
    </div>
    <div class="wcc-jg" id="w1-dept-other-row" style="display:none"><div class="wcc-jl">New dept. name</div><input class="wcc-ji" id="w1-dept-other" placeholder="Type department name" oninput="rs()"></div>
    <div class="wcc-jg"><div class="wcc-jl">Version</div><input class="wcc-ji ro" id="w1-ver" value="v1" readonly></div>
  </div>

  <div class="wcc-key">
    <div class="wcc-ki"><div class="wcc-kd" style="background:var(--blue-bg);border:1px solid #aac"></div>Fixed rate — dbl-click for formula</div>
    <div class="wcc-ki"><div class="wcc-kd" style="background:var(--yellow);border:1px solid #d4b800"></div>User input (enter exact amount)</div>
    <div class="wcc-ki"><div class="wcc-kd" style="background:var(--green-bg);border:1px solid #8bc34a"></div>Auto-rounded (CEILING) — dbl-click for formula</div>
    <div class="wcc-ki"><div class="wcc-kd" style="background:#e8e4f5;border:1px solid #5b3e96"></div>Locked rate (from existing WCC, won't change with future rate updates)</div>
  </div>

  <div class="wcc-topbar">
    <div class="wcc-tbgroup">
      <span>Markup % (applies to all parts):</span>
      <input class="wcc-i" id="markup" type="number" min="0" max="500" step="0.1" value="45" oninput="calc();rs()">%
    </div>
    <div class="wcc-tbgroup" style="margin-left:auto">
      <span>Currency:</span>
      <span style="font-weight:400">from</span>
      <input class="wcc-curcode-i wcc-cur-from" id="curFrom" value="USD" list="curlist" oninput="applyCurrency()" title="Supplier / entered currency — Unit Price column 1 is in this currency">
      <span style="font-weight:400">→</span>
      <input class="wcc-curcode-i wcc-cur-to" id="curTo" value="MYR" list="curlist" oninput="applyCurrency()" title="Converted currency — Unit Price column 2, totals, BPE & WCC2 are in this currency">
      <datalist id="curlist"><option value="MYR"><option value="USD"><option value="EUR"><option value="GBP"><option value="SGD"><option value="AUD"><option value="JPY"><option value="CNY"><option value="INR"><option value="THB"><option value="IDR"><option value="AED"></datalist>
      <span style="font-weight:400">rate (1 from =</span>
      <input class="wcc-i wcc-cur-rate" id="curRate" type="number" min="0" step="0.000001" value="1" placeholder="e.g. 4.7" title="Type the conversion rate: 1 [from] = ? [to]" oninput="applyCurrency()">
      <span style="font-weight:400">to)</span>
      <button class="wcc-cur-btn" id="curReset" onclick="resetCurrency()" title="Reset to MYR 1:1" style="background:#6b7280">Reset</button>
      <span class="wcc-cur-status" id="curStatus" style="font-size:9px;color:#667;font-weight:400"></span>
    </div>
  </div>

  <table class="wcc-t" id="w1TopHead"><thead><tr><th style="width:30px">No.</th><th class="l">Description / Role</th><th style="width:46px">Qty</th><th style="width:44px">UOM</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-from" value="USD" readonly tabindex="-1">)</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-to" value="MYR" readonly tabindex="-1">)</th><th style="width:80px">Total</th><th class="l" style="width:120px">Remarks</th><th style="width:20px"></th></tr></thead></table>
  <div class="wcc-sh a">A — ITEMS / EQUIPMENT / MATERIALS<button class="wcc-ab" onclick="addRow('A')">+ Add line</button></div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:46px">Qty</th><th style="width:44px">UOM</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-from" value="USD" readonly tabindex="-1">)</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-to" value="MYR" readonly tabindex="-1">)</th><th style="width:80px">Total</th><th class="l" style="width:120px">Remarks</th><th style="width:20px"></th></tr></thead>
    <tbody id="A"><tr><td class="no">A1.1</td><td><textarea class="wcc-it dsc" rows="1" placeholder="Item..." oninput="grow(this);rs()"></textarea></td>
      <td class="c"><input class="wcc-i qty" type="number" min="0" oninput="calc();rs()"></td>
      <td class="c"><input class="wcc-i uomx" type="text" value="lot" oninput="calc();rs()"></td>
      <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
      <td><input class="wcc-i wcc-iw pto" type="number" min="0" oninput="lnkTo(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
      <td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Unit Price">RM 0.00</span></td>
      <td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>
      <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total A</td><td class="n" id="Asub" style="width:80px">RM 0.00</td><td></td><td></td></tr></tbody></table>

  <div class="wcc-sh b">B — PROVISION OF MANPOWER — DAILY SALARY<span class="wcc-facwrap"><input id="standbyPct" type="number" min="0" step="0.1" value="95" oninput="calc();rs()" title="Standby rate = role rate × this %"><b>% standby</b><span style="font-size:9px;font-weight:400;color:#cfe">· OT uses an editable formula (double-click an OT rate)</span></span><button class="wcc-ab" onclick="addMP()">+ Add manpower</button></div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Role (editable)</th><th style="width:44px">Qty</th><th style="width:44px">UOM</th><th style="width:80px">Rate (<input class="wcc-hdr-cur curcode-from" value="USD" readonly tabindex="-1">)</th><th style="width:80px">Rate (<input class="wcc-hdr-cur curcode-to" value="MYR" readonly tabindex="-1">)</th><th style="width:80px">Total</th><th class="l" style="width:120px">Remarks</th><th style="width:20px"></th></tr></thead>
    <tbody id="B"><tr data-role="custom"><td class="no">B1.1</td>
      <td><select class="wcc-sel mprole" onchange="loadRole(this)">
          <option value="Engineer">Engineer</option>
          <option value="Technician">Technician</option>
          <option value="Mech. Fitter (x2)">Mech. Fitter (x2)</option>
          <option value="Supervisor">Supervisor</option>
          <option value="Rigger">Rigger</option>
          <option value="Freelancer">Freelancer</option>
          <option value="Diver">Diver</option>
          <option value="__other">Other (type name)…</option>
        </select>
        <input class="wcc-i mpname" type="text" value="Engineer" placeholder="Type role name" oninput="calc();rs()" style="display:none;margin-top:3px">
        <div class="wcc-sbbar"><button class="wcc-sbb" onclick="addChild(this,'sb')" title="Add a standby row for this role">+ Standby</button><button class="wcc-sbb on" onclick="addChild(this,'ot')" title="Add an OT row for this role (choose onshore / offshore)">+ OT</button></div></td>
      <td class="c"><input class="wcc-i mpqty" type="number" min="0" value="1" oninput="calc();rs()"></td>
      <td class="c"><input class="wcc-i mpuom" type="text" value="days" oninput="calc();rs()"></td>
      <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" step="0.01" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
      <td><input class="wcc-i wcc-iw mprate pto" type="number" min="0" step="0.01" value="230" oninput="lnkTo(this)" ondblclick="fpop(event,this)" title="Editable — defaults to the fixed computed rate (RM230/day)"></td>
      <td class="n"><span class="wcc-ca2 rt">RM 230.00</span></td>
      <td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>
      <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total B</td><td class="n" id="Bsub" style="width:80px">RM 0.00</td><td></td><td></td></tr></tbody></table>

  <div class="wcc-sh c">C — MOBILIZATION &amp; DEMOBILIZATION<button class="wcc-ab" onclick="addRow('C')">+ Add line</button></div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:46px">Qty</th><th style="width:44px">UOM</th><th style="width:78px">Rate (<input class="wcc-hdr-cur curcode-from" value="USD" readonly tabindex="-1">)</th><th style="width:78px">Rate (<input class="wcc-hdr-cur curcode-to" value="MYR" readonly tabindex="-1">)</th><th style="width:80px">Total</th><th class="l" style="width:120px">Remarks</th><th style="width:20px"></th></tr></thead>
    <tbody id="C">
      <tr><td class="no">C1.1</td><td><textarea class="wcc-it dsc" rows="1" oninput="grow(this);rs()">Accommodation - Homestay</textarea></td>
        <td class="c"><input class="wcc-i qty" type="number" min="0" value="2" oninput="calc();rs()"></td>
        <td class="c"><input class="wcc-i uomx" type="text" value="days" oninput="calc();rs()"></td>
        <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
        <td><input class="wcc-i wcc-iw pto" type="number" min="0" value="247" oninput="lnkTo(this)" ondblclick="fpop(event,this)"></td>
        <td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Rate, CEILING to nearest 10">RM 0.00</span></td>
        <td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>
        <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr>
      <tr><td class="no">C1.2</td><td><textarea class="wcc-it dsc" rows="1" oninput="grow(this);rs()">Fuel during the job</textarea></td>
        <td class="c"><input class="wcc-i qty" type="number" min="0" value="2" oninput="calc();rs()"></td>
        <td class="c"><input class="wcc-i uomx" type="text" value="days" oninput="calc();rs()"></td>
        <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
        <td><input class="wcc-i wcc-iw pto" type="number" min="0" value="148" oninput="lnkTo(this)" ondblclick="fpop(event,this)"></td>
        <td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Rate, CEILING to nearest 10">RM 0.00</span></td>
        <td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>
        <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr>
      <tr><td class="no">C1.3</td><td><textarea class="wcc-it dsc" rows="1" oninput="grow(this);rs()">Internal Car Rental - ROI</textarea></td>
        <td class="c"><input class="wcc-i qty" type="number" min="0" value="1" oninput="calc();rs()"></td>
        <td class="c"><input class="wcc-i uomx" type="text" value="days" oninput="calc();rs()"></td>
        <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
        <td><input class="wcc-i wcc-iw pto" type="number" min="0" value="69.23" oninput="lnkTo(this)" ondblclick="fpop(event,this)" title="Car rental (was RM1800/mth ÷ 26)"></td>
        <td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Rate, CEILING to nearest 10">RM 0.00</span></td>
        <td><input class="wcc-i rmk" type="text" value="RM1800/mth ÷ 26 days" oninput="rs()"></td>
        <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total C</td><td class="n" id="Csub" style="width:80px">RM 795.00</td><td></td><td></td></tr></tbody></table>

  <div class="wcc-sh de">D — UNPLANNED ITEM<button class="wcc-ab" onclick="addRow('D')">+ Add line</button></div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:46px">Qty</th><th style="width:44px">UOM</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-from" value="USD" readonly tabindex="-1">)</th><th style="width:80px">Unit Price (<input class="wcc-hdr-cur curcode-to" value="MYR" readonly tabindex="-1">)</th><th style="width:80px">Total</th><th class="l" style="width:120px">Remarks</th><th style="width:20px"></th></tr></thead>
    <tbody id="D"><tr><td class="no">D1.1</td><td><textarea class="wcc-it dsc" rows="1" placeholder="Unplanned item..." oninput="grow(this);rs()"></textarea></td>
      <td class="c"><input class="wcc-i qty" type="number" min="0" oninput="calc();rs()" placeholder="0"></td>
      <td class="c"><input class="wcc-i uomx" type="text" value="lot" oninput="calc();rs()"></td>
      <td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
      <td><input class="wcc-i wcc-iw pto" type="number" min="0" oninput="lnkTo(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>
      <td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Unit Price">RM 0.00</span></td>
      <td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>
      <td><button class="wcc-db" onclick="dr(this)">x</button></td></tr></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total D</td><td class="n" id="Dsub" style="width:80px">RM 0.00</td><td></td><td></td></tr></tbody></table>
  <div class="wcc-sh de">E — DISCOUNT RESERVE (% of estimated selling price)</div>
  <div style="background:#fdf8f0;padding:6px 9px;font-size:9px;color:#888;border-bottom:1px solid #e8d8a0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span>Reserve = % of (Subtotal A+B+C+D × (1+markup%)). Same % used in BPE Revision 2 = same RM amount.</span>
  </div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Tier</th><th style="width:50px">%</th><th style="width:120px">Est. Sell Price</th><th colspan="2">Reserve Amt</th></tr></thead>
    <tbody>
      <tr class="wcc-dr"><td class="no">E1</td><td>1st Tier</td><td class="c"><input class="wcc-i" id="d1" type="number" min="0" max="100" step="0.01" value="1" oninput="calc();rs()"></td><td class="n" id="d1b" style="font-size:10px;color:#888">-</td><td colspan="2" class="n"><span class="wcc-ca2" id="d1a" style="cursor:default">RM 0.00</span></td></tr>
      <tr class="wcc-dr"><td class="no">E2</td><td>2nd Tier</td><td class="c"><input class="wcc-i" id="d2" type="number" min="0" max="100" step="0.01" value="0" oninput="calc();rs()"></td><td class="n" id="d2b" style="font-size:10px;color:#888">-</td><td colspan="2" class="n"><span class="wcc-ca2" id="d2a" style="cursor:default">RM 0.00</span></td></tr>
      <tr class="wcc-dr"><td class="no">E3</td><td>3rd Tier</td><td class="c"><input class="wcc-i" id="d3" type="number" min="0" max="100" step="0.01" value="0" oninput="calc();rs()"></td><td class="n" id="d3b" style="font-size:10px;color:#888">-</td><td colspan="2" class="n"><span class="wcc-ca2" id="d3a" style="cursor:default">RM 0.00</span></td></tr>
    </tbody></table>
  <div style="padding:4px 9px;background:#fdf8f0;font-size:9px;color:#7d5400;font-style:italic">Total reserve = <strong id="dpct">3.00%</strong> of est. selling price RM <span id="esel">0.00</span>.</div>

  <div class="wcc-sh de">F — CONTINGENCY</div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:55px">%</th><th colspan="2">Amount</th></tr></thead>
    <tbody><tr class="wcc-dr"><td class="no">F1</td><td>Contingency (% of A+B+C+D)</td><td class="c"><input class="wcc-i" id="e1" type="number" min="0" max="100" step="0.01" oninput="calc();rs()" placeholder="0"></td><td colspan="2" class="n"><span class="wcc-ca2" id="e1a" style="cursor:default">RM 0.00</span></td></tr></tbody></table>

  <table class="wcc-t" style="margin-top:6px"><tbody>
    <tr class="wcc-str"><td style="padding:6px 8px">Sub-total (A+B+C+D)</td><td class="n" id="w1sub" style="width:80px">RM 0.00</td><td></td></tr>
    <tr class="wcc-str"><td style="padding:6px 8px">Less: Discount Reserve (E)</td><td class="n" id="w1disc">RM 0.00</td><td></td></tr>
    <tr class="wcc-str"><td style="padding:6px 8px">Plus: Contingency (F)</td><td class="n" id="w1cont">RM 0.00</td><td></td></tr>
    <tr class="wcc-gtr"><td style="padding:7px 8px">TOTAL PLANNED COST (RM)</td><td class="n" id="w1grand">RM 0.00</td><td></td></tr>
  </tbody></table>
  <div class="wcc-lk">E reserve = est. selling price basis. When BPE Revision 2 applies the same %, RM amount matches exactly.</div>

  <div class="wcc-stampbar" data-doc="w1"><span class="wcc-stamp-h">🧷 Stamps &amp; movable signatures:</span><button class="wcc-stamp-b" onclick="uploadStamp('w1')">＋ Upload stamp / signature</button><button class="wcc-stamp-b alt" onclick="drawFloat('w1')">✍ Draw signature</button><span class="wcc-stamp-tip">drag to move anywhere • drag the corner to resize</span></div>
  <div class="wcc-sb"><div class="wcc-sd">By BDS Dept :</div><div class="wcc-sg c4" id="sgw1"></div></div>
  <div class="wcc-act">
    <button class="wcc-btn" onclick="manualSave('WCC1')">Save</button>
    <button class="wcc-btn" onclick="printDoc('w1')">🖨 Preview / PDF</button>
    <button class="wcc-btn wcc-btnp" onclick="markStatus('WCC1','Submitted for review')">Submit</button>
    <div class="wcc-dbg" id="dbgw1">Marked Done</div>
    <button class="wcc-done" id="dbw1" onclick="toggleDone('w1')">Done</button>
  </div>
</div></div>
</div>

<div class="wcc-pane" data-p="bpe">
<div class="wcc-scroll"><div class="wcc-doc">
  @include('wcc.letterhead')
  <div class="wcc-dt">BPE PRICE SHEET — CUSTOMER QUOTATION</div>
  <div class="wcc-cn2">Selling prices only — internal cost and markup figures never shown here.</div>
  <div class="wcc-ms"><label style="font-weight:700;color:var(--navy)">Format:</label>
    <select id="bpemode" onchange="sbm();rs()" style="padding:4px 8px;font-size:11px;border:1px solid #999;border-radius:4px">
      <option value="detailed">Option 1 — Detailed (line-by-line selling price)</option>
      <option value="lump">Option 2 — Lump Sum (totals only, editable)</option></select></div>
  <div style="background:#fff">
    <div class="wcc-jg"><div class="wcc-jl">Date</div><input class="wcc-ji ln" id="bpedate" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Quo No.</div><input class="wcc-ji ln" id="bpequo" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Client Name</div><input class="wcc-ji ln" id="bpeclient" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Contract No.</div><input class="wcc-ji ln" id="bpecontract" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Project Title</div><input class="wcc-ji ln" id="bpedesc" readonly></div>
  </div>
  <div class="wcc-lk">All header fields linked live from WCC1. Cannot be edited here.</div>

  <div id="bpedet">
    <div class="wcc-internal" style="background:var(--amber-bg);border-bottom:1px solid #e0c060;padding:7px 10px;font-size:11px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <span style="font-weight:700;color:var(--navy)">Min margin threshold:</span>
      <input class="wcc-i" id="minm" type="number" min="0" max="100" step="0.1" value="30" oninput="calc();rs()" style="width:46px">%
    </div>
    <div class="wcc-sh a">A — ITEMS / EQUIPMENT / MATERIALS</div>
    <table class="wcc-t"><thead><tr><th style="width:34px">No.</th><th class="l">Description</th><th style="width:50px">Qty</th><th style="width:50px">UOM</th><th style="width:90px">Unit Price</th><th style="width:95px">Total Price</th></tr></thead>
      <tbody id="bA"></tbody></table>
    <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="5" style="text-align:right;padding-right:8px">Sub-total A</td><td class="n" id="bAs" style="width:95px">RM 0.00</td></tr></tbody></table>

    <div class="wcc-sh b">B — PROVISION OF MANPOWER — DAILY SALARY</div>
    <table class="wcc-t"><thead><tr><th style="width:34px">No.</th><th class="l">Description</th><th style="width:50px">Qty</th><th style="width:50px">UOM</th><th style="width:90px">Unit Price</th><th style="width:95px">Total Price</th></tr></thead>
      <tbody id="bB"></tbody></table>
    <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="5" style="text-align:right;padding-right:8px">Sub-total B</td><td class="n" id="bBs" style="width:95px">RM 0.00</td></tr></tbody></table>

    <div class="wcc-sh c">C — MOBILIZATION &amp; DEMOBILIZATION</div>
    <table class="wcc-t"><thead><tr><th class="l">Description</th><th style="width:130px">Total Price</th></tr></thead>
      <tbody><tr><td>Provision of mobilization &amp; demobilization as per scope of work.</td><td class="n" id="bCl" style="font-weight:700">RM 0.00</td></tr></tbody></table>
    <table class="wcc-t"><tbody><tr class="wcc-str"><td style="text-align:right;padding-right:8px">Sub-total C</td><td class="n" id="bCs" style="width:130px">RM 0.00</td></tr></tbody></table>

    <div class="wcc-sh de">D — UNPLANNED ITEM</div>
    <table class="wcc-t"><thead><tr><th style="width:34px">No.</th><th class="l">Description</th><th style="width:50px">Qty</th><th style="width:50px">UOM</th><th style="width:90px">Unit Price</th><th style="width:95px">Total Price</th></tr></thead>
      <tbody id="bD"></tbody></table>
    <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="5" style="text-align:right;padding-right:8px">Sub-total D</td><td class="n" id="bDs" style="width:95px">RM 0.00</td></tr></tbody></table>

    <table class="wcc-t" style="margin-top:6px"><tbody>
      <tr class="wcc-str"><td style="padding:6px 8px">Total Price (A+B+C+D)</td><td class="n" id="bpetot">RM 0.00</td></tr>
      <tr class="wcc-str"><td style="padding:6px 8px">SST 8%</td><td class="n" id="bpesst">RM 0.00</td></tr>
      <tr class="wcc-gtr"><td style="padding:7px 8px" id="bpegl">GRAND TOTAL PRICE (RM)</td><td class="n" id="bpegrand">RM 0.00</td></tr>
    </tbody></table>

    <div id="bpedsec" style="display:none">
      <div class="wcc-sh disc">REVISION 2 — DISCOUNT (per client request)<button class="wcc-disc-rm" onclick="rmDisc()">Remove</button></div>
      <table class="wcc-t"><thead><tr><th class="l">Description</th><th style="width:90px">% of price</th><th style="width:130px">Amount</th></tr></thead>
        <tbody><tr class="wcc-dr2">
          <td><input class="wcc-it" id="bpeddesc" value="Special discount per client negotiation" oninput="rs()"></td>
          <td class="c"><input class="wcc-i" id="bpedpct" type="number" min="0" max="100" step="0.01" value="10" oninput="calc();rs()"></td>
          <td class="n"><span class="wcc-ca2" id="bpedamt" style="color:var(--red);background:var(--red-bg);cursor:default">- RM 0.00</span></td></tr></tbody></table>
      <div style="padding:5px 9px;font-size:9px;font-style:italic" id="bpedmatch"></div>
      <table class="wcc-t" style="margin-top:6px"><tbody><tr class="wcc-gtr" style="background:var(--red)"><td style="padding:7px 8px">REVISED GRAND TOTAL (RM)</td><td class="n" id="bpegrand2">RM 0.00</td></tr></tbody></table>
    </div>
    <div class="wcc-disc-banner" id="bpedadd">
      <span>Client requested a discount? Add Revision 2 — funded by WCC1 Section D reserve.</span>
      <button class="wcc-disc-add" onclick="addDisc()">+ Add Discount (Rev 2)</button>
    </div>
  </div>

  <div id="bpelump" style="display:none">
    <div style="padding:10px" id="lumpcards"></div>
    <table class="wcc-t"><tbody><tr class="wcc-gtr"><td style="padding:7px 8px">GRAND TOTAL — LUMP SUM (RM)</td><td class="n" id="lumpgt" style="width:110px">RM 0.00</td></tr></tbody></table>
  </div>

  <div class="wcc-pn" style="text-align:left;padding:6px 10px;color:#3a7;border:1px dashed #bcd;border-radius:6px;background:#f6fbf8">This is the client-facing quotation. Internal cost, markup and margin figures are intentionally not shown here — view them in the Manager tab.</div>

  <div class="wcc-tc">
    <div class="wcc-tc-h">Terms &amp; Conditions <span class="wcc-tc-tag edit">editable</span></div>
    <textarea class="wcc-tc-edit" id="bpeTerms" oninput="rs()" spellcheck="false" rows="9">Currency        : Price quoted in MYR
Payment Terms   : 100% Upon PO Issuance ( 14 Days Term)
                : Interest will be charged on overdue accounts at the rate 1.5% per month
Validity        : 30 Days
Delivery        : DDP - DULANG
Lead time       : 3-5 Weeks ARO
Notes           : Cancellation Charges Policy - 45% after 2 weeks from signed PO date, 80% after more than 2 weeks
                : Final claim for manpower shall be based on the approved actual timesheet.
                : Manpower claim is subjected to 8% SST and does not apply to the quoted goods</textarea>

    <div class="wcc-tc-h" style="margin-top:11px">Notes on Quotation <span class="wcc-tc-tag ro">fixed</span></div>
    <div class="wcc-tc-fixed">
      <ol>
        <li>Any deviation in quantity shall be quoted separately.</li>
        <li>Prices are not valid if there are any changes in the equipment brand or rating and will require a revise offer.</li>
        <li>No cancellation of any Purchase Order shall be accepted.</li>
        <li>Any additional site work will be claim as per time sheet.</li>
        <li>Any taxes or duties subjected to Malaysian law shall be imposed from time to time.</li>
      </ol>
      <p>We hope you will find our quotation satisfactory and we look forward to your favourable reply soon. Should you need any information or clarification, please do not hesitate to contact us.</p>
    </div>
  </div>

  <div class="wcc-stampbar" data-doc="bpe"><span class="wcc-stamp-h">🧷 Stamps &amp; movable signatures:</span><button class="wcc-stamp-b" onclick="uploadStamp('bpe')">＋ Upload stamp / signature</button><button class="wcc-stamp-b alt" onclick="drawFloat('bpe')">✍ Draw signature</button><span class="wcc-stamp-tip">drag to move anywhere • drag the corner to resize</span></div>
  <div class="wcc-sb"><div class="wcc-sd">Approved for Issuance :</div><div class="wcc-sg c1" id="sgbpe"></div></div>
  <div class="wcc-pn">Selling prices only. No cost/markup/margin in client PDF export.</div>
  <div class="wcc-act">
    <button class="wcc-btn" onclick="manualSave('BPE Price draft')">Save</button>
    <button class="wcc-btn wcc-btnp" onclick="printDoc('bpe')">🖨 Preview PDF Example</button>
    <span style="font-size:9px;color:#889;align-self:center;max-width:240px;line-height:1.3">Tip: in the print dialog turn <b>OFF “Headers and footers”</b> so no date/file name appears on the client PDF.</span>
    <button class="wcc-btn wcc-btng" onclick="markStatus('BPE Price Sheet','Approved &amp; Issued')">Issue</button>
    <div class="wcc-dbg" id="dbgbpe">Marked Done</div>
    <button class="wcc-done" id="dbbpe" onclick="toggleDone('bpe')">Done</button>
  </div>
  <div class="wcc-po-banner" id="poBanner">🔒 Purchase Order confirmed — WCC1 &amp; BPE Price Sheet are locked and can no longer be edited.</div>
  <div class="wcc-po-wrap">
    <button class="wcc-po-btn" id="poBtn" onclick="openPO()">PURCHASE ORDER CONFIRMED</button>
  </div>
</div></div>
</div>

<div class="wcc-pane" data-p="w2">
<div class="wcc-scroll"><div class="wcc-doc">
  @include('wcc.letterhead')
  <div class="wcc-dt">WORK COMPLETION CERTIFICATE (WCC2) — ACTUAL COST</div>
  <div style="background:#fff">
    <div class="wcc-jg"><div class="wcc-jl">Date</div><input class="wcc-ji wcc-date" id="w2date" type="date" oninput="calc();rs()" onclick="dpick(this)" ondblclick="dpick(this)" onfocus="dpick(this)" title="Click anywhere in the box to open the calendar"></div>
    <div class="wcc-jg"><div class="wcc-jl">Quo No.</div><input class="wcc-ji ro" id="w2quo" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Contract No.</div><input class="wcc-ji ro" id="w2contract" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Client Name</div><input class="wcc-ji ro" id="w2client" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Project Title</div><input class="wcc-ji ro" id="w2desc" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Manager</div><input class="wcc-ji ro" id="w2mgr" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">Department</div><input class="wcc-ji ro" id="w2dept" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">WCC1 Ref.</div><input class="wcc-ji ro" id="w2ref" readonly></div>
    <div class="wcc-jg"><div class="wcc-jl">BPE Sales Total</div><input class="wcc-ji ro" id="w2bpes" readonly></div>
  </div>
  <div class="wcc-lk">Planned Qty + Rate pre-filled from WCC1. Enter Actual Qty only. Dbl-click rate for formula.</div>

  <div class="wcc-sh a">A — ITEMS / EQUIPMENT</div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:38px">UOM</th><th style="width:50px;background:var(--blue-bg);color:var(--navy)">Plan</th><th style="width:50px;background:#ffe;color:#555">Actual</th><th style="width:70px">Rate</th><th style="width:78px">Total</th><th style="width:78px">Variance</th></tr></thead>
    <tbody id="w2A"></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total A</td><td class="n" id="2As" style="width:78px">RM 0.00</td><td class="n" id="2Av" style="width:78px">-</td></tr></tbody></table>

  <div class="wcc-sh b">B — PROVISION OF MANPOWER</div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:38px">UOM</th><th style="width:50px;background:var(--blue-bg);color:var(--navy)">Plan</th><th style="width:50px;background:#ffe;color:#555">Actual</th><th style="width:70px">Rate</th><th style="width:78px">Total</th><th style="width:78px">Variance</th></tr></thead>
    <tbody id="w2B"></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total B</td><td class="n" id="2Bs" style="width:78px">RM 0.00</td><td class="n" id="2Bv" style="width:78px">-</td></tr></tbody></table>

  <div class="wcc-sh c">C — MOBILIZATION &amp; DEMOBILIZATION</div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:38px">UOM</th><th style="width:50px;background:var(--blue-bg);color:var(--navy)">Plan</th><th style="width:50px;background:#ffe;color:#555">Actual</th><th style="width:70px">Rate</th><th style="width:78px">Total</th><th style="width:78px">Variance</th></tr></thead>
    <tbody id="w2C"></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total C</td><td class="n" id="2Cs" style="width:78px">RM 0.00</td><td class="n" id="2Cv" style="width:78px">-</td></tr></tbody></table>

  <div class="wcc-sh de">D — UNPLANNED ITEM</div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description</th><th style="width:38px">UOM</th><th style="width:50px;background:var(--blue-bg);color:var(--navy)">Plan</th><th style="width:50px;background:#ffe;color:#555">Actual</th><th style="width:70px">Rate</th><th style="width:78px">Total</th><th style="width:78px">Variance</th></tr></thead>
    <tbody id="w2D"></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total D</td><td class="n" id="2Ds" style="width:78px">RM 0.00</td><td class="n" id="2Dv" style="width:78px">-</td></tr></tbody></table>

  <div class="wcc-sh de">EXTRA — UNPLANNED ITEMS<button class="wcc-ab" onclick="addX()">+ Add</button></div>
  <table class="wcc-t"><thead><tr><th style="width:30px">No.</th><th class="l">Description / Reason</th><th style="width:38px">UOM</th><th style="width:50px">Plan</th><th style="width:50px;background:#ffe;color:#555">Actual</th><th style="width:70px">Rate</th><th style="width:78px">Total</th><th style="width:78px">Variance</th></tr></thead>
    <tbody id="w2X"></tbody></table>
  <table class="wcc-t"><tbody><tr class="wcc-str"><td colspan="6" style="text-align:right;padding-right:8px">Sub-total Unplanned</td><td class="n" id="2Xs" style="width:78px">RM 0.00</td><td class="n" id="2Xv" style="width:78px">-</td></tr></tbody></table>

  <table class="wcc-t" style="margin-top:6px"><tbody><tr class="wcc-gtr"><td colspan="6" style="padding:7px 8px">TOTAL ACTUAL COST (RM)</td><td class="n" id="2tot">RM 0.00</td><td class="n" id="2var">-</td></tr></tbody></table>

  <div class="wcc-mb" style="background:var(--blue-bg);border-top:2px solid var(--navy-light)">
    <div style="padding:5px 10px;font-size:10px;font-weight:700;color:var(--navy)">VARIANCE ANALYSIS vs WCC1</div>
    <div class="wcc-mg" style="border-top:1px solid #ccd9e8">
      <div class="wcc-mi" style="border-color:#ccd9e8"><label>Planned</label><div class="wcc-mv" id="2mp">RM 0.00</div></div>
      <div class="wcc-mi" style="border-color:#ccd9e8"><label>Actual</label><div class="wcc-mv" id="2ma">RM 0.00</div></div>
      <div class="wcc-mi" style="border-color:#ccd9e8"><label>Variance</label><div class="wcc-mv" id="2mv">-</div></div>
      <div class="wcc-mi" style="border-color:#ccd9e8"><label>Variance %</label><div class="wcc-mv" id="2mvp">-</div></div>
      <div class="wcc-mi"><label>Actual Margin %</label><div class="wcc-mv" id="2mam">-</div></div>
    </div>
  </div>

  <div class="wcc-stampbar" data-doc="w2"><span class="wcc-stamp-h">🧷 Stamps &amp; movable signatures:</span><button class="wcc-stamp-b" onclick="uploadStamp('w2')">＋ Upload stamp / signature</button><button class="wcc-stamp-b alt" onclick="drawFloat('w2')">✍ Draw signature</button><span class="wcc-stamp-tip">drag to move anywhere • drag the corner to resize</span></div>
  <div class="wcc-sb"><div class="wcc-sd">By BDS Dept :</div><div class="wcc-sg c4" id="sgw2"></div></div>
  <div class="wcc-act">
    <button class="wcc-btn" onclick="manualSave('WCC2')">Save</button>
    <button class="wcc-btn wcc-btnp" onclick="printDoc('w2')">🖨 Preview / PDF</button>
    <button class="wcc-btn" onclick="attachW2()">Attach</button>
    <button class="wcc-btn wcc-btnp" onclick="markStatus('WCC2','Submitted for review')">Submit</button>
    <div class="wcc-dbg" id="dbgw2">Marked Done</div>
    <button class="wcc-done" id="dbw2" onclick="toggleDone('w2')">Done</button>
  </div>
</div></div>
</div>

<div class="wcc-pane" data-p="mgr">
<div style="background:var(--green);color:#fff;padding:9px 14px;font-size:12px;font-weight:700">Senior Management View — full cost, price, profit and margin</div>
<div style="padding:12px">
  <div class="wcc-mg" style="border:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:12px">
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;text-align:center;background:#fff"><label style="font-size:9px;color:#888;text-transform:uppercase">Total Cost</label><div style="font-size:20px;font-weight:800;color:var(--navy)" id="gC">RM 0.00</div></div>
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;text-align:center;background:#fff"><label style="font-size:9px;color:#888;text-transform:uppercase">Selling Price</label><div style="font-size:20px;font-weight:800;color:var(--green)" id="gS">RM 0.00</div><div style="font-size:9px;color:#aaa" id="gSs">Incl. SST</div></div>
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;text-align:center;background:#fff"><label style="font-size:9px;color:#888;text-transform:uppercase">Profit Margin</label><div style="font-size:20px;font-weight:800;color:var(--navy)" id="gM">RM 0.00</div></div>
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;text-align:center;background:#fff"><label style="font-size:9px;color:#888;text-transform:uppercase">Margin %</label><div style="font-size:20px;font-weight:800;color:var(--navy)" id="gMp">0.0%</div></div>
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;text-align:center;background:#fff"><label style="font-size:9px;color:#888;text-transform:uppercase">Status</label><div style="font-size:13px;font-weight:800;color:var(--navy)" id="gSt">Pre-Execution</div></div>
  </div>
  <div id="gDisc" style="display:none;text-align:left;margin-bottom:12px;border-left:4px solid var(--red);border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff">
    <label style="font-size:10px;color:#888">Revision 2 Discount Applied</label>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px"><span id="gDd" style="color:#555"></span><span id="gDa" style="font-weight:800;color:var(--red);font-size:16px"></span></div>
  </div>
  <div class="wcc-mw" id="gW" style="margin-bottom:12px"></div>
  <table class="wcc-t" style="font-size:11px"><thead><tr><th class="l">Section</th><th>Cost</th><th>Sales</th><th>Profit</th><th>Margin%</th></tr></thead><tbody id="gT"></tbody></table>

  <div style="margin-top:16px;border-top:2px solid var(--navy);padding-top:10px">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <div style="font-size:12px;font-weight:800;color:var(--navy)">📊 PERFORMANCE CHARTS — quotation value won per Department &amp; per Manager</div>
      <button class="wcc-ub" style="background:#eee;color:#555;border:1px solid #ccc;margin-left:auto" onclick="clearRegbook()" title="Erase the chart history saved in this browser">🗑 Clear chart history</button>
    </div>
    <div style="font-size:9px;color:#778;margin:4px 0 8px">Every WCC with a Quo No. is registered automatically in this browser. Value is credited to <b>both</b> its Department and its Manager — so a manager gets credit even when the job sits under another department (e.g. OMAR doing an Electrical proposal).</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="wcc-chartgrid">
      <div><div style="font-size:10px;font-weight:700;color:var(--navy);margin-bottom:5px">By Department</div><div id="chDept"></div></div>
      <div><div style="font-size:10px;font-weight:700;color:var(--navy);margin-bottom:5px">By Manager</div><div id="chMgr"></div></div>
    </div>
  </div>
</div>
</div>

<div class="wcc-pane" data-p="rates">
<div style="padding:12px 14px;background:var(--blue-bg)">
  <div style="font-size:13px;font-weight:700;color:var(--navy)">Manpower Rate Formulas — live rates for NEW WCC1s</div>
  <div style="font-size:10px;color:#555;margin-top:2px">Changing these affects only WCC1s created after the change. Existing WCC1/WCC2 documents keep their locked-in rate at creation time (see Rate History below).</div>
</div>
<div style="padding:12px">
  <table class="wcc-t" style="margin-bottom:10px">
    <thead><tr><th class="l">Role</th><th>Raw salary/mth (RM)</th><th>Work days/mth</th><th>OT mult.</th><th class="l">Formula</th><th>Rate</th></tr></thead>
    <tbody>
      <tr><td>Engineer</td><td><input class="wcc-i wcc-iw" id="resal" type="number" value="5000" oninput="calc();rs()"></td><td><input class="wcc-i" id="red" type="number" value="26" style="width:44px" oninput="calc();rs()"></td><td><input class="wcc-i" id="rem" type="number" value="1.15" step="0.01" style="width:44px" oninput="calc();rs()"></td><td style="font-size:9px;font-style:italic" id="ref">=CEILING(5000/26/8x8x115%,10)</td><td style="background:var(--blue-bg)" id="rer">RM 230/day</td></tr>
      <tr><td>Engineer OT</td><td colspan="3" style="font-size:9px;font-style:italic">=Daily/8x1.5, ceil</td><td style="font-size:9px;font-style:italic" id="reof">=CEILING(230/8x1.5,1)</td><td style="background:var(--blue-bg)" id="reor">RM 44/hr</td></tr>
      <tr><td>Technician</td><td><input class="wcc-i wcc-iw" id="rtsal" type="number" value="3000" oninput="calc();rs()"></td><td><input class="wcc-i" id="rtd" type="number" value="26" style="width:44px" oninput="calc();rs()"></td><td><input class="wcc-i" id="rtm" type="number" value="1.15" step="0.01" style="width:44px" oninput="calc();rs()"></td><td style="font-size:9px;font-style:italic" id="rtf">=CEILING(3000/26/8x8x115%,10)</td><td style="background:var(--blue-bg)" id="rtr">RM 140/day</td></tr>
      <tr><td>Technician OT</td><td colspan="3" style="font-size:9px;font-style:italic">=Daily/8x1.5, ceil</td><td style="font-size:9px;font-style:italic" id="rtof">=CEILING(140/8x1.5,1)</td><td style="background:var(--blue-bg)" id="rtor">RM 27/hr</td></tr>
      <tr><td>Mech. Fitter (x2)</td><td><input class="wcc-i wcc-iw" id="rfr" type="number" value="28" oninput="calc();rs()"></td><td colspan="2" style="font-size:9px;font-style:italic">RM/hr x8x2</td><td style="font-size:9px;font-style:italic" id="rff">=28x8x2</td><td style="background:var(--blue-bg)" id="rfr2">RM 448/day</td></tr>
      <tr><td>Mech. Fitter OT (x2)</td><td colspan="3" style="font-size:9px;font-style:italic">=Daily/8x1.5, ceil</td><td style="font-size:9px;font-style:italic" id="rfof">=CEILING(448/8x1.5,1)</td><td style="background:var(--blue-bg)" id="rfor">RM 84/hr</td></tr>
      <tr><td>Car Rental</td><td><input class="wcc-i wcc-iw" id="rcm" type="number" value="1800" oninput="calc();rs()"></td><td><input class="wcc-i" id="rcd" type="number" value="26" style="width:44px" oninput="calc();rs()"></td><td style="font-size:9px;font-style:italic">per day</td><td style="font-size:9px;font-style:italic" id="rcf">=1800/26</td><td style="background:var(--blue-bg)" id="rcr">RM 69.23/day</td></tr>
    </tbody>
  </table>
  <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:6px">Rate change history (effective dates)</div>
  <table class="wcc-t"><thead><tr><th class="l">Date</th><th class="l">Change</th><th class="l">Affects</th></tr></thead>
    <tbody id="rateHistTable">
      <tr><td>13/05/2026</td><td>Initial setup — Engineer RM5000/mth, Technician RM3000/mth</td><td>All WCC1s created on/after this date</td></tr>
    </tbody></table>
  <div class="wcc-pn" style="text-align:left;padding:8px 0">Existing WCC1/WCC2 line items store a snapshot of the rate and formula used at creation time (shown with a purple "locked" badge). They are unaffected by edits made here.</div>
</div>
</div>

</div>

<div id="sigmo" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:18px;width:340px;max-width:90vw;box-shadow:0 10px 40px rgba(0,0,0,.3)">
    <h3 style="font-size:14px;color:var(--navy);margin-bottom:10px">Add your signature</h3>
    <input type="text" id="mn" placeholder="Your full name" style="width:100%;padding:5px 8px;font-size:11px;border:1px solid #ccc;border-radius:3px;margin-bottom:6px">
    <input type="text" id="mt3" placeholder="Title / designation (optional)" style="width:100%;padding:5px 8px;font-size:11px;border:1px solid #ccc;border-radius:3px;margin-bottom:10px">
    <div style="display:flex;gap:4px;margin-bottom:10px;border-bottom:1px solid #ddd">
      <div class="sigtab on" data-tab="draw" style="flex:1;padding:6px;text-align:center;font-size:11px;cursor:pointer;border-bottom:2px solid var(--navy);font-weight:700;color:var(--navy)">Draw</div>
      <div class="sigtab" data-tab="upload" style="flex:1;padding:6px;text-align:center;font-size:11px;cursor:pointer;border-bottom:2px solid transparent;color:#888">Upload</div>
    </div>
    <div id="stdraw">
      <canvas id="sigcanvas" style="border:1px dashed #999;border-radius:4px;width:100%;height:120px;touch-action:none;cursor:crosshair;background:#fafafa"></canvas>
    </div>
    <div id="stupload" style="display:none">
      <div id="suarea" style="border:2px dashed #ccc;border-radius:6px;padding:20px;text-align:center;font-size:11px;color:#888;cursor:pointer">
        <div id="suph">Click to upload signature image</div><img id="supreview" style="display:none;max-height:90px;max-width:100%">
      </div>
      <input type="file" id="sfile" accept="image/*" style="display:none">
    </div>
    <div style="display:flex;gap:6px;margin-top:12px;justify-content:flex-end">
      <button id="sigclear" style="padding:6px 12px;font-size:11px;border:1px solid #e99;color:#c00;border-radius:4px;cursor:pointer;background:#fff">Clear</button>
      <button id="sigcancel" style="padding:6px 12px;font-size:11px;border:1px solid #999;border-radius:4px;cursor:pointer;background:#fff">Cancel</button>
      <button id="sigsave" style="padding:6px 12px;font-size:11px;border:1px solid var(--navy);border-radius:4px;cursor:pointer;background:var(--navy);color:#fff">Apply</button>
    </div>
  </div>
</div>

<input type="file" id="stampfile" accept="image/*" style="display:none">
<input type="file" id="xlsxfile" accept=".xlsx,.xls" style="display:none">

<div id="pomo" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9100;align-items:center;justify-content:center">
  <div class="wcc-pomo-card">
    <div class="wcc-pomo-head"><span>Confirm Purchase Order</span><button class="wcc-pomo-x" onclick="closePO()" title="Exit">&times;</button></div>
    <div class="wcc-pomo-body">Once the Purchase Order is confirmed, <strong>WCC1</strong> and the <strong>BPE Price Sheet</strong> will become read-only and can no longer be edited. Do you want to proceed?</div>
    <div class="wcc-pomo-foot">
      <button class="wcc-pomo-no" onclick="closePO()">No</button>
      <button class="wcc-pomo-yes" onclick="confirmPO()">Yes, confirm</button>
    </div>
  </div>
</div>
