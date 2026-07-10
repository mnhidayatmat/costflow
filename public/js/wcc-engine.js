(function(){
const root=document.getElementById('wcc-root');
/* PATCHED vs. the original prototype: several WCC2 elements have ids that
   begin with a digit (#2As, #2tot, #2mam, …). Those are legal HTML ids but
   illegal CSS identifiers, so querySelector('#2As') throws a SyntaxError.
   calc() aborted there every run, which silently killed the WCC2 subtotals,
   the variance analysis, updateLump() and buildMgr(). CSS.escape fixes it. */
function byId(id){return root.querySelector('#'+CSS.escape(String(id)));}
const MP={};let RC={A:1,B:1,C:1,D:1,X:0};
let curSig=null,upSig=null;const SS={};let discOn=false;
let hist=[],hi=-1,restoring=false;
const doneMap={w1:false,bpe:false,w2:false};
let lineSeq=0;

const SGCFG={
  w1:[{id:'sw1-1',role:'Prepared by :',name:'Gaayathre A/P Bala Chendran',title:'(Proposal Engineer)'},
      {id:'sw1-2',role:'Verified by :',name:'Mohd Azwan Bin Khatib',title:'(Sr. Manager, Operational Excellence)'},
      {id:'sw1-3',role:'Reviewed by :',name:'Khairul Azizi Bin Sa\'aban',title:'(Manager, Instrumentation)'},
      {id:'sw1-4',role:'Approved by :',name:'Datuk Ir. Ts. Mohd Isnari B. Idris',title:'(Managing Director)'}],
  bpe:[{id:'sbpe-1',role:'Approved by :',name:'Datuk Ir. Ts. Mohd Isnari B. Idris',title:'(Managing Director)'}],
  w2:[{id:'sw2-1',role:'Prepared by :',name:'Gaayathre A/P Bala Chendran',title:'(Proposal Engineer)'},
      {id:'sw2-2',role:'Verified by :',name:'Mohd Azwan Bin Khatib',title:'(Sr. Manager, Operational Excellence)'},
      {id:'sw2-3',role:'Reviewed by :',name:'Khairul Azizi Bin Sa\'aban',title:'(Manager, Instrumentation)'},
      {id:'sw2-4',role:'Approved by :',name:'Datuk Ir. Ts. Mohd Isnari B. Idris',title:'(Managing Director)'}],
};

function bsg(){
  Object.entries(SGCFG).forEach(([doc,slots])=>{
    const g=root.querySelector('#sg'+doc);if(!g)return;g.innerHTML='';
    slots.forEach(sl=>{
      const c=document.createElement('div');c.className='wcc-sc';
      c.innerHTML='<div class="wcc-sr">'+sl.role+'</div>'+
        '<div class="wcc-sl" id="'+sl.id+'"><span class="ph">Click to sign</span></div>'+
        '<input class="wcc-sni" id="'+sl.id+'-n" value="'+sl.name+'" placeholder="Full name">'+
        '<input class="wcc-sti" id="'+sl.id+'-t" value="'+sl.title+'" placeholder="Designation">'+
        '<div class="wcc-sco"><button class="wcc-qb" id="'+sl.id+'-q" data-sig="'+sl.id+'">QuickSign</button>'+
        '<button class="wcc-szb" id="'+sl.id+'-sm" data-resize="'+sl.id+'" data-d="-1">A-</button>'+
        '<button class="wcc-szb" id="'+sl.id+'-sp" data-resize="'+sl.id+'" data-d="1">A+</button></div>';
      g.appendChild(c);
    });
  });
}

const ct=n=>Math.ceil(n/10)*10;
let CUR={from:'MYR',to:'MYR',rate:1,symFrom:'RM',symTo:'RM'};
const fm=v=>CUR.symTo+' '+((v||0)).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
const fmF=v=>CUR.symFrom+' '+((v||0)).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
const fp2=v=>(v*100).toFixed(1)+'%';
function vv(id){const e=byId(id);return parseFloat(e&&e.value)||0;}
function st(id,val){const e=byId(id);if(e)e.textContent=val;}

function otFormula(kind){return kind==='otoff'?'CEILING.MATH((salary/12)*1.5,1)':'CEILING.MATH((salary/8)*1.5,1)';}
window.otFormula=otFormula;
const XL_HELP='Functions: CEILING.MATH, FLOOR, ROUND, ROUNDUP, ROUNDDOWN, MIN, MAX, SUM, AVERAGE, ABS, INT, MOD, SQRT, POWER.  ^ = power,  n% = percent.';
function fpop(e,el){
  document.querySelectorAll('.wcc-fp').forEach(function(p){p.remove();});
  const p=document.createElement('div');p.className='wcc-fp';p.style.minWidth='280px';p.style.maxWidth='400px';
  const tr=el.closest('tr');
  const isTotal=!!(el.classList&&el.classList.contains('rt'));
  /* BPE derived cells: VIEW-ONLY formula (how the amount was calculated) */
  const isView=(el.tagName!=='INPUT')&&!isTotal&&el.dataset&&el.dataset.formula&&el.closest&&el.closest('.wcc-pane[data-p="bpe"],.wcc-pane[data-p="w2"]');
  if(isView){
    const inW2=!!el.closest('.wcc-pane[data-p="w2"]');
    p.innerHTML='<div style="font-size:10px;color:#fff;margin-bottom:5px;font-weight:700">How this amount is calculated</div>'+
      '<div style="font-family:ui-monospace,monospace;font-size:11px;color:#ffe9a8;line-height:1.55;word-break:break-word">'+el.dataset.formula+'</div>'+
      '<div style="font-size:8.5px;color:rgba(255,255,255,.7);margin-top:7px;line-height:1.4">'+(inW2?'View-only — derived from WCC1 plan and your Actual quantities.':'View-only — derived automatically from WCC1 costs × markup. Internal: this popup never appears in the client print.')+'</div>';
    document.body.appendChild(p);
    const rc=el.getBoundingClientRect();p.style.left=Math.min(rc.left,window.innerWidth-410)+'px';p.style.top=(rc.bottom+8)+'px';
    setTimeout(function(){document.addEventListener('click',function c(ev){if(!p.contains(ev.target)){p.remove();document.removeEventListener('click',c);}});},10);
    e.stopPropagation();return;
  }
  const isQty=!!(el.classList&&(el.classList.contains('qty')||el.classList.contains('mpqty')));
  const sbtype=tr?tr.dataset.sbtype:null;
  const isOT=(el.tagName==='INPUT')&&(sbtype==='oton'||sbtype==='otoff')&&(el.classList.contains('pto')||el.classList.contains('pfrom'));
  let cur,vhelp,deflt;
  if(isTotal){deflt='CEILING(q*r,10)';cur=tr.dataset.fover||deflt;vhelp='Variables: <b>q</b> = qty, <b>r</b> = unit price ('+CUR.to+').';}
  else if(isOT){deflt=otFormula(sbtype);cur=el.dataset.fover||deflt;vhelp='Variables: <b>salary</b> = the linked role\'s rate. (Onshore \u00f78, Offshore \u00f712.)';}
  else if(isQty){deflt='';cur=el.dataset.fover||'';vhelp='Variables: <b>r</b> = unit price (MYR), <b>salary</b>, <b>rate</b>. Leave blank to type the qty manually.';}
  else{deflt='';cur=el.dataset.fover||'';vhelp='Variables: <b>q</b> = qty, <b>r</b> = unit price (MYR), <b>salary</b>, <b>rate</b> = FX rate. Leave blank to type the value manually.';}
  p.innerHTML='<div style="font-size:10px;margin-bottom:5px;line-height:1.45">Enter an Excel-style formula \u2014 type a few letters (e.g. <b>cei</b>) for suggestions. '+vhelp+'</div>'+
    '<div style="font-size:9px;color:#ffe9a8;margin-bottom:5px;line-height:1.45">\ud83d\udcCC Like Excel: <b>click any Qty / Unit Price / Total cell</b> in the sheet to insert its reference (e.g. <b>A2P</b> = Section A \u00b7 row 2 \u00b7 Price MYR). Letters: Q qty \u00b7 F price col 1 \u00b7 P price MYR \u00b7 T total \u00b7 ASUB\u2026DSUB sub-totals.</div>';
  const wrap=document.createElement('div');wrap.style.cssText='position:relative;margin-bottom:6px';
  const inp=document.createElement('input');inp.type='text';inp.value=cur;inp.placeholder='= e.g. CEILING.MATH(q*r,10)';
  inp.style.cssText='width:100%;box-sizing:border-box;font-family:ui-monospace,monospace;font-size:11px;padding:5px 6px;border:1px solid #888;border-radius:3px';
  inp.setAttribute('autocomplete','off');inp.setAttribute('spellcheck','false');
  wrap.appendChild(inp);
  /* Excel-like click-to-reference: while this editor is open, clicking a Qty/Price/Total cell inserts its reference */
  window.__fpopInput={inp:inp};
  document.body.classList.add('wcc-refmode');
  function cleanupRef(){document.body.classList.remove('wcc-refmode');if(window.__fpopInput&&window.__fpopInput.inp===inp)window.__fpopInput=null;}
  /* ---- Excel-like function autocomplete ---- */
  const XL_SUGS=[
    {n:'CEILING.MATH',h:'(x, step) \u2014 round up to step'},
    {n:'CEILING',h:'(x, step)'},
    {n:'FLOOR.MATH',h:'(x, step) \u2014 round down to step'},
    {n:'FLOOR',h:'(x, step)'},
    {n:'ROUND',h:'(x, digits)'},
    {n:'ROUNDUP',h:'(x, digits)'},
    {n:'ROUNDDOWN',h:'(x, digits)'},
    {n:'MIN',h:'(a, b, \u2026)'},
    {n:'MAX',h:'(a, b, \u2026)'},
    {n:'SUM',h:'(a, b, \u2026)'},
    {n:'AVERAGE',h:'(a, b, \u2026)'},
    {n:'ABS',h:'(x)'},
    {n:'INT',h:'(x)'},
    {n:'MOD',h:'(a, b)'},
    {n:'SQRT',h:'(x)'},
    {n:'POWER',h:'(x, y)'},
    {n:'q',h:'qty of this row',v:1},
    {n:'r',h:'unit price (MYR)',v:1},
    {n:'salary',h:'linked role rate',v:1},
    {n:'rate',h:'FX conversion rate',v:1}
  ];
  const acBox=document.createElement('div');
  acBox.style.cssText='display:none;position:absolute;left:0;right:0;top:100%;z-index:99999;background:#fff;border:1px solid #7a8bb5;border-radius:0 0 4px 4px;box-shadow:0 8px 20px rgba(0,0,0,.22);max-height:170px;overflow-y:auto;font-size:11px';
  wrap.appendChild(acBox);
  let acItems=[],acSel=0,acStart=0,acLen=0;
  function acClose(){acBox.style.display='none';acItems=[];}
  function acRender(){
    acBox.innerHTML='';
    acItems.forEach(function(sug,i){
      const it=document.createElement('div');
      it.style.cssText='padding:4px 8px;cursor:pointer;display:flex;gap:8px;align-items:baseline'+(i===acSel?';background:#dbe6ff':'');
      it.innerHTML='<b style="font-family:ui-monospace,monospace;color:'+(sug.v?'#1a7a3c':'#143d7a')+'">'+sug.n+'</b><span style="color:#778;font-size:9.5px">'+sug.h+'</span>';
      it.onmousedown=function(ev){ev.preventDefault();acPick(i);};
      it.onmouseenter=function(){acSel=i;acRender();};
      acBox.appendChild(it);
    });
    acBox.style.display=acItems.length?'block':'none';
  }
  function acShow(){
    const pos=(inp.selectionStart==null?inp.value.length:inp.selectionStart);
    const before=inp.value.slice(0,pos);
    const m=before.match(/([A-Za-z_][A-Za-z_.]*)$/);
    if(!m){acClose();return;}
    const w=m[1].toUpperCase();
    acStart=pos-m[1].length;acLen=m[1].length;
    acItems=XL_SUGS.filter(function(sug){const N=sug.n.toUpperCase();return N.indexOf(w)===0&&N!==w;});
    acSel=0;acRender();
  }
  function acPick(i){
    const sug=acItems[i];if(!sug)return;
    const after=inp.value.slice(acStart+acLen);
    const insert=sug.v?sug.n:(sug.n+'(');
    inp.value=inp.value.slice(0,acStart)+insert+after;
    const np=acStart+insert.length;
    acClose();inp.focus();inp.setSelectionRange(np,np);
  }
  inp.addEventListener('input',acShow);
  inp.addEventListener('click',acShow);
  inp.onkeydown=function(ev){
    if(acBox.style.display!=='none'&&acItems.length){
      if(ev.key==='ArrowDown'){acSel=(acSel+1)%acItems.length;acRender();ev.preventDefault();return;}
      if(ev.key==='ArrowUp'){acSel=(acSel-1+acItems.length)%acItems.length;acRender();ev.preventDefault();return;}
      if(ev.key==='Tab'||ev.key==='Enter'){acPick(acSel);ev.preventDefault();return;}
      if(ev.key==='Escape'){acClose();ev.preventDefault();ev.stopPropagation();return;}
    }
    if(ev.key==='Enter')apply();
    if(ev.key==='Escape'){cleanupRef();p.remove();}
  };
  p.appendChild(wrap);
  function apply(){
    const v=inp.value.trim();
    if(isTotal){if(v&&v.replace(/\s/g,'').toUpperCase()!==deflt.replace(/\s/g,'').toUpperCase())tr.dataset.fover=v;else delete tr.dataset.fover;}
    else{if(v){el.dataset.fover=v;el.dataset.fcustom='1';}else{delete el.dataset.fover;delete el.dataset.fcustom;el.readOnly=false;}}
    calc();rs();cleanupRef();p.remove();
  }
  function reset(){
    if(isTotal){delete tr.dataset.fover;}
    else if(isOT){el.dataset.fover=otFormula(sbtype);delete el.dataset.fcustom;}
    else{delete el.dataset.fover;delete el.dataset.fcustom;el.readOnly=false;}
    calc();rs();cleanupRef();p.remove();
  }
  const bar=document.createElement('div');bar.style.cssText='display:flex;gap:6px;justify-content:flex-end';
  const rst=document.createElement('button');rst.textContent=isOT?'Default':'Reset';rst.style.cssText='font-size:10px;padding:3px 10px;background:#888;color:#fff;border:none;border-radius:3px;cursor:pointer';rst.onclick=reset;
  const ap=document.createElement('button');ap.textContent='Apply';ap.style.cssText='font-size:10px;padding:3px 12px;background:var(--navy,#1f3864);color:#fff;border:none;border-radius:3px;cursor:pointer';ap.onclick=apply;
  bar.appendChild(rst);bar.appendChild(ap);p.appendChild(bar);
  const note=document.createElement('div');note.style.cssText='font-size:8.5px;color:rgba(255,255,255,.75);margin-top:6px;line-height:1.4';note.textContent=XL_HELP;p.appendChild(note);
  document.body.appendChild(p);
  const rc=el.getBoundingClientRect();p.style.left=Math.min(rc.left,window.innerWidth-410)+'px';p.style.top=(rc.bottom+8)+'px';
  setTimeout(function(){inp.focus();inp.select();document.addEventListener('click',function c(ev){if(!p.contains(ev.target)){cleanupRef();p.remove();document.removeEventListener('click',c);}});},10);
  e.stopPropagation();
}
window.fpop=fpop;

/* ---- Tab switching + action buttons made functional (print/PDF, save, submit, attach) ---- */
function showTab(tab){
  root.querySelectorAll('.wcc-tab').forEach(function(x){x.classList.toggle('on',x.dataset.t===tab);});
  root.querySelectorAll('.wcc-pane').forEach(function(p){p.classList.toggle('on',p.dataset.p===tab);});
  if(window.__gridifyVisible)setTimeout(window.__gridifyVisible,0);
}
window.showTab=showTab;

function wccToast(msg,ok){
  let el=document.getElementById('wccToast');
  if(!el){el=document.createElement('div');el.id='wccToast';el.style.cssText='position:fixed;left:50%;bottom:28px;transform:translateX(-50%);color:#fff;padding:10px 18px;border-radius:8px;font:600 13px system-ui,Arial,sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.28);z-index:99999;opacity:0;transition:opacity .2s;max-width:82vw;text-align:center';document.body.appendChild(el);}
  el.style.background=(ok===false)?'#a3341f':'#1f3864';
  el.textContent=msg;requestAnimationFrame(function(){el.style.opacity='1';});
  clearTimeout(el._t);el._t=setTimeout(function(){el.style.opacity='0';},2800);
}
window.wccToast=wccToast;

function printDoc(which){
  showTab(which);
  setTimeout(function(){
    if(window.__unifyHeaders){try{window.__unifyHeaders();}catch(e){}}
    if(window.growAll){try{growAll();}catch(e){}}
    /* expand every text box to its full content so nothing is clipped on the printed page */
    try{root.querySelectorAll('textarea').forEach(function(t){t.style.height='auto';t.style.height=(t.scrollHeight+2)+'px';});}catch(e){}
    /* professional print title (this is what shows if the browser prints a header) */
    window.__prevTitle=document.title;
    try{
      const quo=((root.querySelector('#w1-quo')||{}).value||'').trim();
      const client=((root.querySelector('#w1-client')||{}).value||'').trim();
      if(which==='bpe')document.title='BPE Energy Sdn. Bhd. — Quotation'+(quo?' '+quo:'')+(client?' — '+client:'');
      else if(which==='w1')document.title='WCC1 — Planned Budget'+(quo?' ('+quo+')':'');
      else if(which==='w2')document.title='WCC2 — Actual Cost'+(quo?' ('+quo+')':'');
    }catch(e){}
    document.body.classList.add('wcc-print-mode');
    setTimeout(function(){window.print();},150);
  },80);
}
window.printDoc=printDoc;
window.addEventListener('afterprint',function(){
  document.body.classList.remove('wcc-print-mode');
  if(window.__prevTitle){document.title=window.__prevTitle;window.__prevTitle=null;}
});

function manualSave(which){
  try{saveLS(cap());const s=root.querySelector('#saveStat');if(s){s.textContent='💾 saved';setTimeout(function(){s.textContent='💾 auto-saved';},1600);}wccToast((which||'Document')+' saved in this browser ✓');}
  catch(e){wccToast('Could not save: '+e,false);}
}
window.manualSave=manualSave;

function markStatus(doc,status){wccToast(doc+' marked as “'+status+'”.  (Front-end prototype — connect a backend to route it onward.)');}
window.markStatus=markStatus;

function attachW2(){
  let inp=document.getElementById('w2attach');
  if(!inp){inp=document.createElement('input');inp.type='file';inp.id='w2attach';inp.style.display='none';
    inp.onchange=function(){if(inp.files&&inp.files[0])wccToast('Attached: '+inp.files[0].name+'  (prototype — kept for this session only)');};
    document.body.appendChild(inp);}
  inp.value='';inp.click();
}
window.attachW2=attachW2;

function syncAttrs(scope){
  scope.querySelectorAll('input,select,textarea').forEach(function(el){
    if(el.type==='checkbox'||el.type==='radio'){el.checked?el.setAttribute('checked','checked'):el.removeAttribute('checked');}
    else if(el.tagName==='SELECT'){Array.prototype.forEach.call(el.options,function(o){o.selected?o.setAttribute('selected','selected'):o.removeAttribute('selected');});}
    else if(el.tagName==='TEXTAREA'){el.textContent=el.value;}
    else{el.setAttribute('value',el.value);}
  });
}
function cap(){
  const s={sec:{},fields:{},sigs:{},SS:{},sn:{},st2:{},mode:(root.querySelector('#bpemode')||{}).value||'detailed',lump:{},discOn:discOn,doneMap:Object.assign({},doneMap)};
  ['A','B','C','D'].forEach(function(id){
    s.sec[id]=[];root.querySelectorAll('#'+id+' tr').forEach(function(tr){syncAttrs(tr);s.sec[id].push({role:tr.dataset.role||null,fover:tr.dataset.fover||null,rid:tr.dataset.rid||null,sbof:tr.dataset.standbyOf||null,sbtype:tr.dataset.sbtype||null,manual:tr.dataset.manual||null,html:tr.innerHTML});});
  });
  s.sec.w2X=[];root.querySelectorAll('#w2X tr').forEach(function(tr){syncAttrs(tr);s.sec.w2X.push({html:tr.innerHTML});});
  root.querySelectorAll('input[id],select[id],textarea[id]').forEach(function(el){if(el.id)s.fields[el.id]=el.value;});
  root.querySelectorAll('#lumpcards [data-li]').forEach(function(el){s.lump[el.dataset.li]=el.value;});
  root.querySelectorAll('.wcc-sl').forEach(function(el){const img=el.querySelector('img');s.sigs[el.id]=img?img.src:null;s.SS[el.id]=SS[el.id]||44;});
  root.querySelectorAll('.wcc-sni').forEach(function(el){s.sn[el.id]=el.value;});
  root.querySelectorAll('.wcc-sti').forEach(function(el){s.st2[el.id]=el.value;});
  s.floats=[];root.querySelectorAll('.wcc-float').forEach(function(f){s.floats.push({doc:f.dataset.doc,src:f.dataset.src,x:parseFloat(f.style.left)||0,y:parseFloat(f.style.top)||0,w:f.offsetWidth,h:f.offsetHeight});});
  return JSON.stringify(s);
}

function rest(str){
  restoring=true;const s=JSON.parse(str);
  ['A','B','C','D'].forEach(function(id){
    const tb=root.querySelector('#'+id);tb.innerHTML='';
    (s.sec[id]||[]).forEach(function(r){const tr=document.createElement('tr');if(r.role)tr.dataset.role=r.role;if(r.fover)tr.dataset.fover=r.fover;if(r.rid)tr.dataset.rid=r.rid;if(r.sbof)tr.dataset.standbyOf=r.sbof;if(r.sbtype)tr.dataset.sbtype=r.sbtype;if(r.manual)tr.dataset.manual=r.manual;tr.innerHTML=r.html;tb.appendChild(tr);});
  });
  const xt=root.querySelector('#w2X');if(xt){xt.innerHTML='';(s.sec.w2X||[]).forEach(function(r){const tr=document.createElement('tr');tr.innerHTML=r.html;xt.appendChild(tr);});}
  Object.entries(s.fields||{}).forEach(function(kv){const e=root.querySelector('#'+kv[0]);if(e&&e.tagName!=='SPAN')e.value=kv[1];});
  const me=root.querySelector('#bpemode');if(me)me.value=s.mode||'detailed';
  sbm(true);
  Object.entries(s.lump||{}).forEach(function(kv){const e=root.querySelector('[data-li="'+kv[0]+'"]');if(e)e.value=kv[1];});
  Object.entries(s.sigs||{}).forEach(function(kv){
    const id=kv[0],src=kv[1];const el=root.querySelector('#'+id);const sz=(s.SS||{})[id]||44;SS[id]=sz;
    if(el){
      if(src){el.innerHTML='<img src="'+src+'" style="height:'+sz+'px">';el.classList.add('signed');
        const q=root.querySelector('#'+id+'-q');if(q){q.textContent='Signed';q.classList.add('ok');}
        [id+'-sm',id+'-sp'].forEach(function(b){const x=root.querySelector('#'+b);if(x)x.style.display='inline-block';});
      }else{el.innerHTML='<span class="ph">Click to sign</span>';el.classList.remove('signed');
        const q=root.querySelector('#'+id+'-q');if(q){q.textContent='QuickSign';q.classList.remove('ok');}
        [id+'-sm',id+'-sp'].forEach(function(b){const x=root.querySelector('#'+b);if(x)x.style.display='none';});
      }
    }
  });
  Object.entries(s.sn||{}).forEach(function(kv){const e=root.querySelector('#'+kv[0]);if(e)e.value=kv[1];});
  Object.entries(s.st2||{}).forEach(function(kv){const e=root.querySelector('#'+kv[0]);if(e)e.value=kv[1];});
  Object.entries(s.doneMap||{}).forEach(function(kv){
    doneMap[kv[0]]=kv[1];
    const btn=root.querySelector('#db'+kv[0]),badge=root.querySelector('#dbg'+kv[0]);
    if(btn){btn.textContent=kv[1]?'Edit':'Done';btn.className=kv[1]?'wcc-done on':'wcc-done';}
    if(badge)badge.className=kv[1]?'wcc-dbg on':'wcc-dbg';
  });
  discOn=!!s.discOn;
  const ds=root.querySelector('#bpedsec'),da=root.querySelector('#bpedadd');
  if(ds)ds.style.display=discOn?'block':'none';
  if(da)da.style.display=discOn?'none':'flex';
  root.querySelectorAll('.wcc-float').forEach(function(f){f.remove();});
  (s.floats||[]).forEach(function(o){try{makeFloat(o.doc,o.src,o.x,o.y,o.w,o.h);}catch(e){}});
  try{calc();}catch(e){console.error('calc in rest:',e);}
  try{updateLump();}catch(e){}
  restoring=false;
  if(window.growAll){try{growAll();}catch(e){}}
}

function rs(){
  if(restoring)return;
  let snap;try{snap=cap();}catch(e){console.error('snapshot (cap) failed:',e);return;}
  if(hist[hi]===snap)return;
  hist=hist.slice(0,hi+1);hist.push(snap);hi=hist.length-1;
  if(hist.length>80){hist.shift();hi--;}
  ubtn();try{saveLS(snap);}catch(e){}
}
window.rs=rs;window.__wccCap=cap;window.__wccRest=rest;
const LS_KEY='costflow_wcc_state_v7';
let lsTimer=null;
function saveLS(snap){
  clearTimeout(lsTimer);
  lsTimer=setTimeout(function(){
    try{localStorage.setItem(LS_KEY,snap);const s=root.querySelector('#saveStat');if(s){s.textContent='💾 auto-saved';s.style.color='#1a7a3c';}}
    catch(e){const s=root.querySelector('#saveStat');if(s){s.textContent='⚠ save failed (storage full?)';s.style.color='#c00';}}
  },400);
}
function loadLS(){try{return localStorage.getItem(LS_KEY);}catch(e){return null;}}
function clearSaved(){
  if(!confirm('Erase the data saved in this browser and reload a blank template? This cannot be undone.'))return;
  try{localStorage.removeItem(LS_KEY);}catch(e){}
  location.reload();
}
window.clearSaved=clearSaved;
function doUndo(){if(hi<=0){ubtn();return;}hi--;try{rest(hist[hi]);}catch(e){console.error('undo failed:',e);}ubtn();}
function doRedo(){if(hi>=hist.length-1){ubtn();return;}hi++;try{rest(hist[hi]);}catch(e){console.error('redo failed:',e);}ubtn();}
window.doUndo=doUndo;window.doRedo=doRedo;
function ubtn(){
  const u=root.querySelector('#ub-u'),r=root.querySelector('#ub-r');
  if(u){u.classList.toggle('off',hi<=0);}
  if(r){r.classList.toggle('off',hi>=hist.length-1);}
}

function toggleDone(k){
  doneMap[k]=!doneMap[k];const v=doneMap[k];
  const btn=root.querySelector('#db'+k),badge=root.querySelector('#dbg'+k);
  if(btn){btn.textContent=v?'Edit':'Done';btn.className=v?'wcc-done on':'wcc-done';}
  if(badge)badge.className=v?'wcc-dbg on':'wcc-dbg';
  rs();
}
window.toggleDone=toggleDone;

function crates(){
  const es=vv('resal'),ed=vv('red')||26,em=vv('rem')||1.15;
  const ts=vv('rtsal'),td=vv('rtd')||26,tm=vv('rtm')||1.15;
  const fr=vv('rfr'),cm=vv('rcm'),cd=vv('rcd')||26;
  MP.eD=ct(es/ed/8*8*em);MP.eO=Math.ceil(MP.eD/8*1.5);
  MP.tD=ct(ts/td/8*8*tm);MP.tO=Math.ceil(MP.tD/8*1.5);
  MP.fD=fr*8*2;MP.fO=Math.ceil(MP.fD/8*1.5);
  MP.cD=cm/cd;
  st('ref','=CEILING('+es+'/'+ed+'/8x8x'+Math.round(em*100)+'%,10)');st('rer',fm(MP.eD)+'/day');
  st('reof','=CEILING('+MP.eD+'/8x1.5,1)');st('reor',fm(MP.eO)+'/hr');
  st('rtf','=CEILING('+ts+'/'+td+'/8x8x'+Math.round(tm*100)+'%,10)');st('rtr',fm(MP.tD)+'/day');
  st('rtof','=CEILING('+MP.tD+'/8x1.5,1)');st('rtor',fm(MP.tO)+'/hr');
  st('rff','='+fr+'x8x2');st('rfr2',fm(MP.fD)+'/day');
  st('rfof','=CEILING('+MP.fD+'/8x1.5,1)');st('rfor',fm(MP.fO)+'/hr');
  st('rcf','='+cm+'/'+cd);st('rcr',fm(MP.cD)+'/day');
}

const RM={
  engineer:function(){return{r:MP.eD,u:'days',f:'raw RM'+vv('resal')+'/mth -> CEILING('+vv('resal')+'/'+(vv('red')||26)+'/8x8x'+Math.round((vv('rem')||1.15)*100)+'%,10) = RM'+MP.eD+'/day'};},
  engineer_ot:function(){return{r:MP.eO,u:'hours',f:'=CEILING('+MP.eD+'/8x1.5,1) = RM'+MP.eO+'/hr'};},
  technician:function(){return{r:MP.tD,u:'days',f:'raw RM'+vv('rtsal')+'/mth -> CEILING('+vv('rtsal')+'/'+(vv('rtd')||26)+'/8x8x'+Math.round((vv('rtm')||1.15)*100)+'%,10) = RM'+MP.tD+'/day'};},
  technician_ot:function(){return{r:MP.tO,u:'hours',f:'=CEILING('+MP.tD+'/8x1.5,1) = RM'+MP.tO+'/hr'};},
  fitter:function(){return{r:MP.fD,u:'days',f:'='+vv('rfr')+'x8x2 = RM'+MP.fD+'/day'};},
  fitter_ot:function(){return{r:MP.fO,u:'hours',f:'=CEILING('+MP.fD+'/8x1.5,1) = RM'+MP.fO+'/hr'};},
  custom:function(){return{r:null,u:'days',f:'Manual entry - user-defined rate'};},
};

function presetName(role){
  return {engineer:'Engineer',engineer_ot:'Engineer (OT)',technician:'Technician',technician_ot:'Technician (OT)',fitter:'Mech. Fitter (x2)',fitter_ot:'Mech. Fitter OT (x2)'}[role]||'';
}
function loadPreset(sel){
  const role=sel.value;if(!role){return;}
  const info=RM[role]?RM[role]():null;if(!info){sel.value='';return;}
  const tr=sel.closest('tr');
  const nm=tr.querySelector('.mpname');if(nm)nm.value=presetName(role);
  const um=tr.querySelector('.mpuom');if(um)um.value=info.u;
  const rate=info.r||0;
  const rt=tr.querySelector('.pto');
  if(rt){rt.value=rate;rt.title='Loaded preset (editable): '+info.f;}
  const pf=tr.querySelector('.pfrom');if(pf){const r=CUR.rate||1;pf.value=(rate/r).toFixed(2);}
  if(nm&&nm.tagName==='TEXTAREA'&&window.grow)grow(nm);
  sel.value='';
  calc();
}
window.loadPreset=loadPreset;

/* Role dropdown (#3): pick a role; known roles load their preset rate, 'Other' reveals a name box. */
const ROLE_KEY={'Engineer':'engineer','Technician':'technician','Mech. Fitter (x2)':'fitter'};
const ROLE_LABELS=['Engineer','Technician','Mech. Fitter (x2)','Supervisor','Rigger','Freelancer','Diver'];
function loadRole(sel){
  const tr=sel.closest('tr');if(!tr)return;
  const nm=tr.querySelector('.mpname');
  if(sel.value==='__other'){
    if(nm){nm.style.display='block';if(ROLE_LABELS.indexOf(nm.value)>=0)nm.value='';nm.focus&&nm.focus();}
    calc();rs();return;
  }
  if(nm){nm.style.display='none';nm.value=sel.value;}
  const key=ROLE_KEY[sel.value];
  if(key&&RM[key]){const info=RM[key]();const rt=tr.querySelector('.pto');if(rt){rt.value=info.r||0;rt.title='Preset rate (editable): '+info.f;}const um=tr.querySelector('.mpuom');if(um&&info.u)um.value=info.u;}
  const pf=tr.querySelector('.pfrom'),pt=tr.querySelector('.pto');if(pf&&pt){const r=CUR.rate||1;pf.value=r?(cnum(pt.value)/r).toFixed(2):'0';}
  calc();rs();
}
window.loadRole=loadRole;

/* Child rows (Standby / OT) are LINKED to a parent role row. Their rate auto-tracks the
   parent's current (editable) rate × an editable % factor, and is itself editable
   (typing overrides; "↺ auto" restores). OT rows choose Onshore/Offshore inline. */
let bSeq=0;
function addChild(btn,type){
  const parent=btn.closest('tr');if(!parent)return;
  if(parent.dataset.standbyOf){alert('This is already a standby/OT row. Add it from the main role row instead.');return;}
  if(!parent.dataset.rid)parent.dataset.rid='b'+(++bSeq);
  const rid=parent.dataset.rid;
  RC.B++;
  const pname=((parent.querySelector('.mpname')||{}).value||'Role').trim();
  const puom=((parent.querySelector('.mpuom')||{}).value||'days');
  const isOT=(type==='ot');
  const sbtype=isOT?'oton':'sb';
  const label=isOT?'(OT)':'(Standby)';
  const uom=isOT?'hrs':puom;
  let note;
  if(isOT){
    note='↳ OT for <b class="sbparent">'+pname+'</b> — '+
      '<select class="otkind" onchange="otKind(this)"><option value="oton">Onshore</option><option value="otoff">Offshore</option></select>'+
      ' · formula <b class="sbpctlbl">÷8</b> <span style="color:#888">(double-click the rate to view/edit)</span> <a class="sbauto" onclick="sbAuto(this)" title="Reset to default OT formula">↺ default</a>';
  } else {
    note='↳ Standby for <b class="sbparent">'+pname+'</b> = role rate × <b class="sbpctlbl">--</b>% <a class="sbauto" onclick="sbAuto(this)" title="Restore auto-tracking">↺ auto</a>';
  }
  const tr=document.createElement('tr');
  tr.dataset.role='custom';tr.dataset.standbyOf=rid;tr.dataset.sbtype=sbtype;
  tr.innerHTML='<td class="no">↳</td>'+
    '<td><textarea class="wcc-it mpname" rows="1" oninput="grow(this);calc();rs()">'+pname+' '+label+'</textarea>'+
    '<div class="wcc-sbnote">'+note+'</div></td>'+
    '<td class="c"><input class="wcc-i mpqty" type="number" min="0" value="1" oninput="calc();rs()"></td>'+
    '<td class="c"><input class="wcc-i mpuom" type="text" value="'+uom+'" oninput="calc();rs()"></td>'+
    '<td><input class="wcc-i wcc-iw pfrom" type="number" readonly tabindex="-1" ondblclick="fpop(event,this)" title="Auto — derived from the rate"></td>'+
    '<td><input class="wcc-i wcc-iw mprate pto" type="number" min="0" step="0.01" oninput="sbManual(this)" ondblclick="fpop(event,this)" title="Double-click to view/edit the formula"></td>'+
    '<td class="n"><span class="wcc-ca2 rt">RM 0.00</span></td>'+
    '<td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>'+
    '<td><button class="wcc-db" onclick="dr(this)">x</button></td>';
  parent.parentNode.insertBefore(tr,parent.nextSibling);
  if(isOT){const pto=tr.querySelector('.pto');if(pto)pto.dataset.fover=otFormula('oton');}
  calc();rs();
}
window.addChild=addChild;
function otKind(sel){const tr=sel.closest('tr');if(tr){tr.dataset.sbtype=sel.value;const pto=tr.querySelector('.pto');if(pto&&!pto.dataset.fcustom)pto.dataset.fover=otFormula(sel.value);}calc();rs();}
window.otKind=otKind;
function sbManual(el){const tr=el.closest('tr');if(tr){tr.dataset.manual='1';}lnkTo(el);}
window.sbManual=sbManual;
function sbAuto(a){const tr=a.closest('tr');if(tr){const pto=tr.querySelector('.pto');if(tr.dataset.sbtype==='oton'||tr.dataset.sbtype==='otoff'){if(pto){delete pto.dataset.fcustom;pto.dataset.fover=otFormula(tr.dataset.sbtype);}}else{delete tr.dataset.manual;}}calc();rs();}
window.sbAuto=sbAuto;

function addMP(){
  RC.B++;const tb=root.querySelector('#B');const tr=document.createElement('tr');
  tr.dataset.role='custom';
  tr.innerHTML='<td class="no">B1.'+RC.B+'</td>'+
    '<td><select class="wcc-sel mprole" onchange="loadRole(this)">'+
    '<option value="Engineer">Engineer</option><option value="Technician">Technician</option>'+
    '<option value="Mech. Fitter (x2)">Mech. Fitter (x2)</option><option value="Supervisor">Supervisor</option>'+
    '<option value="Rigger">Rigger</option><option value="Freelancer">Freelancer</option>'+
    '<option value="Diver">Diver</option><option value="__other">Other (type name)…</option></select>'+
    '<input class="wcc-i mpname" type="text" value="Engineer" placeholder="Type role name" oninput="calc();rs()" style="display:none;margin-top:3px">'+
    '<div class="wcc-sbbar"><button class="wcc-sbb" onclick="addChild(this,\'sb\')" title="Add a standby row for this role">+ Standby</button><button class="wcc-sbb on" onclick="addChild(this,\'ot\')" title="Add an OT row (choose onshore/offshore)">+ OT</button></div></td>'+
    '<td class="c"><input class="wcc-i mpqty" type="number" min="0" value="1" oninput="calc();rs()"></td>'+
    '<td class="c"><input class="wcc-i mpuom" type="text" value="days" oninput="calc();rs()"></td>'+
    '<td><input class="wcc-i wcc-iw pfrom" type="number" min="0" step="0.01" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>'+
    '<td><input class="wcc-i wcc-iw mprate pto" type="number" min="0" step="0.01" value="'+MP.eD+'" oninput="lnkTo(this)" ondblclick="fpop(event,this)" title="Editable rate (default: Engineer RM'+MP.eD+'/day)"></td>'+
    '<td class="n"><span class="wcc-ca2 rt">'+fm(MP.eD)+'</span></td>'+
    '<td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>'+
    '<td><button class="wcc-db" onclick="dr(this)">x</button></td>';
  tb.appendChild(tr);
  const pf=tr.querySelector('.pfrom'),pt=tr.querySelector('.pto');if(pf&&pt){const r=CUR.rate||1;pf.value=(cnum(pt.value)/r).toFixed(2);}
  calc();rs();
}
window.addMP=addMP;

function addRow(sec){
  RC[sec]++;const tb=root.querySelector('#'+sec);const tr=document.createElement('tr');
  tr.innerHTML='<td class="no">'+sec+'1.'+RC[sec]+'</td><td><textarea class="wcc-it dsc" rows="1" placeholder="Description" oninput="grow(this);rs()"></textarea></td>'+
    '<td class="c"><input class="wcc-i qty" type="number" min="0" oninput="calc();rs()" placeholder="0"></td>'+
    '<td class="c"><input class="wcc-i uomx" type="text" value="lot" oninput="calc();rs()"></td>'+
    '<td><input class="wcc-i wcc-iw pfrom" type="number" min="0" oninput="lnkFrom(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>'+
    '<td><input class="wcc-i wcc-iw pto" type="number" min="0" oninput="lnkTo(this)" ondblclick="fpop(event,this)" placeholder="0.00"></td>'+
    '<td class="n"><span class="wcc-ca2 rt" ondblclick="fpop(event,this)" data-formula="=Qty x Unit Price">RM 0.00</span></td>'+
    '<td><input class="wcc-i rmk" type="text" oninput="rs()" placeholder="—"></td>'+
    '<td><button class="wcc-db" onclick="dr(this)">x</button></td>';
  tb.appendChild(tr);calc();rs();
}
window.addRow=addRow;

function addX(){
  RC.X++;const tb=root.querySelector('#w2X');const tr=document.createElement('tr');
  tr.innerHTML='<td class="no">X.'+RC.X+'</td><td><input class="wcc-it" placeholder="Reason" oninput="rs()"></td>'+
    '<td class="c"><input class="wcc-i" type="text" value="lot" style="width:42px" oninput="calc();rs()"></td>'+
    '<td class="c"><span style="color:#999;font-size:10px">-</span></td>'+
    '<td class="c"><input class="wcc-i" type="number" min="0" oninput="calc();rs()" placeholder="0"></td>'+
    '<td class="c"><input class="wcc-i wcc-iw" type="number" min="0" oninput="calc();rs()" placeholder="0.00"></td>'+
    '<td class="n"><span class="wcc-ca2 rt">RM 0.00</span></td><td class="n"><span class="wcc-vp">New</span></td>';
  tb.appendChild(tr);calc();rs();
}
window.addX=addX;

function dr(btn){const tr=btn.closest('tr');if(tr.parentElement.children.length>1){tr.remove();calc();rs();}}
window.dr=dr;

function cnum(v){return parseFloat(v)||0;}
/* ---- Excel-like CELL REFERENCES ----
   Token = Section + row number (counted from the top of that section) + field letter:
   Q = Qty · F = Unit Price col 1 (From) · P = Unit Price MYR · T = line Total
   e.g. A2P = Section A, 2nd row, Price (MYR).  ASUB..DSUB = section sub-totals.
   Resolved live on every recalc, so formulas stay linked to the current values. */
window.__refVal=function(sec,row,fld){
  try{
    const tb=root.querySelector('#'+String(sec).toUpperCase());if(!tb)return NaN;
    const tr=tb.querySelectorAll('tr')[row-1];if(!tr)return NaN;
    fld=String(fld).toLowerCase();
    if(fld==='q')return cnum((tr.querySelector('.qty,.mpqty')||{}).value);
    if(fld==='p')return cnum((tr.querySelector('.pto')||{}).value);
    if(fld==='f')return cnum((tr.querySelector('.pfrom')||{}).value);
    if(fld==='t')return parseFloat(tr.dataset.t)||0;
    return NaN;
  }catch(e){return NaN;}
};
window.__refSub=function(sec){
  try{let s=0;root.querySelectorAll('#'+String(sec).toUpperCase()+' tr').forEach(function(tr){s+=parseFloat(tr.dataset.t)||0;});return s;}catch(e){return NaN;}
};
/* bidirectional currency link between the two unit-price inputs */
function lnkFrom(el){const tr=el.closest('tr');const to=tr.querySelector('.pto');if(to){const r=CUR.rate||1;to.value=(cnum(el.value)*r).toFixed(2);}calc();rs();}
function lnkTo(el){const tr=el.closest('tr');const from=tr.querySelector('.pfrom');if(from){const r=CUR.rate||1;from.value=r?(cnum(el.value)/r).toFixed(2):'0';}calc();rs();}
window.lnkFrom=lnkFrom;window.lnkTo=lnkTo;
/* ---- Excel-like formula evaluator (#1) ----
   Variables: q (qty), r (unit price MYR), salary (parent role rate for OT/standby rows), rate (FX rate)
   Functions: CEILING / CEILING.MATH, FLOOR / FLOOR.MATH, ROUND, ROUNDUP, ROUNDDOWN,
              MIN, MAX, SUM, AVERAGE, ABS, INT, MOD, SQRT, POWER, and ^ for power, n% for percent. */
const XL_FN={
  CEILING:function(x,n){n=(n==null?1:+n);return n===0?0:Math.ceil(x/n)*n;},
  FLOOR:function(x,n){n=(n==null?1:+n);return n===0?0:Math.floor(x/n)*n;},
  ROUND:function(x,d){d=(d==null?0:+d);var f=Math.pow(10,d);return Math.round(x*f)/f;},
  ROUNDUP:function(x,d){d=(d==null?0:+d);var f=Math.pow(10,d);return Math.ceil(x*f)/f;},
  ROUNDDOWN:function(x,d){d=(d==null?0:+d);var f=Math.pow(10,d);return Math.floor(x*f)/f;},
  MIN:function(){return Math.min.apply(null,arguments);},
  MAX:function(){return Math.max.apply(null,arguments);},
  SUM:function(){var s=0;for(var i=0;i<arguments.length;i++)s+=(+arguments[i]||0);return s;},
  AVERAGE:function(){var s=0,n=arguments.length;for(var i=0;i<n;i++)s+=(+arguments[i]||0);return n?s/n:0;},
  ABS:Math.abs,INT:Math.trunc,MOD:function(a,b){return b?a%b:0;},SQRT:Math.sqrt,POWER:Math.pow
};
const XL_CANON={ceiling:'CEILING',ceilingmath:'CEILING',ceil:'CEILING',floor:'FLOOR',floormath:'FLOOR',round:'ROUND',roundup:'ROUNDUP',rounddown:'ROUNDDOWN',min:'MIN',max:'MAX',sum:'SUM',average:'AVERAGE',avg:'AVERAGE',abs:'ABS',int:'INT',mod:'MOD',sqrt:'SQRT',power:'POWER'};
const XL_VAR={q:'q',r:'r',salary:'salary',rate:'rate',pi:'PI_'};
function xlEval(expr,vars){
  vars=vars||{};
  try{
    var s=String(expr==null?'':expr).trim().replace(/^=+/,'');
    if(!s)return null;
    s=s.replace(/×/g,'*').replace(/÷/g,'/').replace(/\^/g,'**');
    s=s.replace(/CEILING\.MATH/gi,'CEILING').replace(/FLOOR\.MATH/gi,'FLOOR');
    s=s.replace(/(\d+(?:\.\d+)?)\s*%/g,'($1/100)');
    var bad=false;
    s=s.replace(/[A-Za-z_][A-Za-z0-9_.]*/g,function(tok){
      var lo=tok.toLowerCase();
      if(XL_CANON[lo])return XL_CANON[lo];
      if(XL_VAR[lo])return XL_VAR[lo];
      var mR=/^([a-d])([0-9]+)([qpft])$/.exec(lo);
      if(mR){var rv=(window.__refVal?window.__refVal(mR[1],+mR[2],mR[3]):NaN);if(typeof rv==='number'&&isFinite(rv))return '('+rv+')';bad=true;return '\u0000';}
      var mS=/^([a-d])sub$/.exec(lo);
      if(mS){var sv=(window.__refSub?window.__refSub(mS[1]):NaN);if(typeof sv==='number'&&isFinite(sv))return '('+sv+')';bad=true;return '\u0000';}
      bad=true;return '\u0000';
    });
    if(bad)return NaN;
    var names=['CEILING','FLOOR','ROUND','ROUNDUP','ROUNDDOWN','MIN','MAX','SUM','AVERAGE','ABS','INT','MOD','SQRT','POWER','q','r','salary','rate','PI_'];
    var vals=[XL_FN.CEILING,XL_FN.FLOOR,XL_FN.ROUND,XL_FN.ROUNDUP,XL_FN.ROUNDDOWN,XL_FN.MIN,XL_FN.MAX,XL_FN.SUM,XL_FN.AVERAGE,XL_FN.ABS,XL_FN.INT,XL_FN.MOD,XL_FN.SQRT,XL_FN.POWER,(+vars.q||0),(+vars.r||0),(+vars.salary||0),(vars.rate==null?1:+vars.rate),Math.PI];
    var fn=new Function(names.join(','),'"use strict";return ('+s+');');
    var v=fn.apply(null,vals);
    return (typeof v==='number'&&isFinite(v))?v:NaN;
  }catch(e){return NaN;}
}
/* legacy name kept for any existing callers */
function evalF(expr,q,r){var v=xlEval(expr,{q:q,r:r});return isFinite(v)?v:ct(q*r);}

function calc(){
  crates();
  const mu=vv('markup')/100;
  readCUR();
  const RATE=CUR.rate||1;
  root.querySelectorAll('.curcode-from').forEach(function(i){i.value=CUR.from;});
  root.querySelectorAll('.curcode-to').forEach(function(i){i.value=CUR.to;});

  /* parent role rate map first — needed for OT/standby 'salary' (#2) */
  const ridRate={},ridName={};
  root.querySelectorAll('#B tr').forEach(function(tr){if(!tr.dataset.standbyOf&&tr.dataset.rid){ridRate[tr.dataset.rid]=cnum((tr.querySelector('.pto')||{}).value);ridName[tr.dataset.rid]=((tr.querySelector('.mpname')||{}).value)||'role';}});
  function salaryFor(tr){return tr.dataset.standbyOf?(ridRate[tr.dataset.standbyOf]||0):0;}
  function evalInputF(tr,q){
    const sal=salaryFor(tr);
    const rNow=cnum((tr.querySelector('.pto')||{}).value);
    ['.pfrom','.pto','.rmk'].forEach(function(sel){
      const inp=tr.querySelector(sel);if(!inp)return;
      if(inp.dataset.fover){const v=xlEval(inp.dataset.fover,{q:q,r:rNow,salary:sal,rate:RATE});if(isFinite(v))inp.value=Math.round(v*100)/100;inp.readOnly=true;inp.classList.add('hasf');}
      else{inp.classList.remove('hasf');}
    });
  }

  function rowCalc(tr){
    const sal=salaryFor(tr);
    /* qty formula (Excel-like on the Qty column too) */
    const qI=tr.querySelector('.qty,.mpqty');
    if(qI&&qI.dataset.fover){
      const rNow=cnum((tr.querySelector('.pto')||{}).value);
      const v=xlEval(qI.dataset.fover,{q:cnum(qI.value),r:rNow,salary:sal,rate:RATE});
      if(isFinite(v))qI.value=Math.round(v*100)/100;
      qI.readOnly=true;qI.classList.add('hasf');
    }else if(qI){qI.classList.remove('hasf');}
    const q=cnum((qI||{}).value);
    evalInputF(tr,q);
    const ptoI=tr.querySelector('.pto'),pfI=tr.querySelector('.pfrom');
    /* keep the two currency columns consistent when a formula drove one side */
    if(ptoI&&ptoI.dataset.fover&&pfI&&!pfI.dataset.fover){pfI.value=RATE?(cnum(ptoI.value)/RATE).toFixed(2):'0';pfI.readOnly=true;}
    else if(pfI&&pfI.dataset.fover&&ptoI&&!ptoI.dataset.fover){ptoI.value=(cnum(pfI.value)*RATE).toFixed(2);}
    const pto=cnum((ptoI||{}).value);
    const pfrom=cnum((pfI||{}).value);
    let t=tr.dataset.fover?xlEval(tr.dataset.fover,{q:q,r:pto,salary:salaryFor(tr),rate:RATE}):NaN;
    if(!isFinite(t))t=ct(q*pto);
    tr.dataset.q=q;tr.dataset.r=pfrom;tr.dataset.cu=pto;tr.dataset.t=t;
    tr.dataset.d=((tr.querySelector('.dsc,.mpname')||{}).value)||'';
    tr.dataset.u=((tr.querySelector('.uomx,.mpuom')||{}).value)||'';
    const te=tr.querySelector('.rt');
    if(te){te.textContent=fm(t);
      te.dataset.formula=tr.dataset.fover?('custom: '+tr.dataset.fover+' = '+fm(t)):('=CEILING('+q+' × '+pto+',10) = '+fm(t));}
    return t;
  }

  let aT=0;root.querySelectorAll('#A tr').forEach(function(tr){aT+=rowCalc(tr);});st('Asub',fm(aT));
  /* child rows: standby = role rate × standby% (factor);  OT = its own editable formula using salary (#2) */
  root.querySelectorAll('#B tr').forEach(function(tr){
    if(!tr.dataset.standbyOf)return;
    const type=tr.dataset.sbtype||'sb';
    const kindSel=tr.querySelector('.otkind');if(kindSel&&kindSel.value!==type)kindSel.value=type;
    const par=tr.querySelector('.sbparent');if(par)par.textContent=ridName[tr.dataset.standbyOf]||'role';
    const lbl=tr.querySelector('.sbpctlbl');const au=tr.querySelector('.sbauto');
    if(type==='sb'){
      const facV=(vv('standbyPct')||95);const base=ridRate[tr.dataset.standbyOf];
      const pto=tr.querySelector('.pto');
      if(pto&&!pto.dataset.fover){if(!tr.dataset.manual&&base!==undefined)pto.value=Math.round(base*facV/100*100)/100;pto.readOnly=false;}
      const pf=tr.querySelector('.pfrom');if(pf){pf.value=pto?(RATE?(cnum(pto.value)/RATE).toFixed(2):'0'):'';pf.readOnly=true;}
      if(lbl)lbl.textContent=facV;
      if(au)au.style.display=tr.dataset.manual?'inline':'none';
    }else{
      const pto=tr.querySelector('.pto');
      if(pto&&!pto.dataset.fover&&!pto.dataset.fcustom)pto.dataset.fover=otFormula(type);
      if(lbl)lbl.textContent=(type==='otoff'?'÷12':'÷8');
      if(au)au.style.display=(pto&&pto.dataset.fcustom)?'inline':'none';
    }
  });
  let bT=0;root.querySelectorAll('#B tr').forEach(function(tr){bT+=rowCalc(tr);});st('Bsub',fm(bT));
  let cT=0;root.querySelectorAll('#C tr').forEach(function(tr){cT+=rowCalc(tr);});st('Csub',fm(cT));
  let dT=0;root.querySelectorAll('#D tr').forEach(function(tr){dT+=rowCalc(tr);});st('Dsub',fm(dT));

  const sub=aT+bT+cT+dT;st('w1sub',fm(sub));

  const estSell=sub*(1+mu);
  st('esel',estSell.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2}));
  [['d1','d1a','d1b'],['d2','d2a','d2b'],['d3','d3a','d3b']].forEach(function(arr){
    const pct=vv(arr[0])/100,amt=ct(pct*estSell);
    st(arr[1],fm(amt));st(arr[2],estSell>0?fm(estSell):'-');
  });
  const dAmt=ct((vv('d1')+vv('d2')+vv('d3'))/100*estSell);
  const dPct=vv('d1')+vv('d2')+vv('d3');
  st('dpct',dPct.toFixed(2)+'%');st('w1disc',fm(dAmt));
  window._dPct=dPct;window._dAmt=dAmt;window._estSell=estSell;

  const e1=ct(vv('e1')/100*sub);st('e1a',fm(e1));st('w1cont',fm(e1));
  const grand=sub-dAmt+e1;st('w1grand',fm(grand));

  const fmtDate=function(iso){if(!iso)return '';const p=iso.split('-');return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):iso;};
  ['date','quo','client','desc'].forEach(function(f){
    const s=root.querySelector('#w1-'+f);if(!s)return;
    const bd=root.querySelector('#bpe'+f);if(bd)bd.value=(f==='date')?fmtDate(s.value):s.value;
    if(f!=='desc'){const wd=root.querySelector('#w2'+f);if(wd)wd.value=s.value;}
  });
  const deptSel=root.querySelector('#w1-dept');
  const deptOther=root.querySelector('#w1-dept-other');
  const deptVal=deptSel&&deptSel.value==='__other'?(deptOther?deptOther.value:''):(deptSel?deptSel.value:'');
  const bdept=root.querySelector('#bpedept');if(bdept)bdept.value=deptVal;
  const otherRow=root.querySelector('#w1-dept-other-row');
  if(otherRow)otherRow.style.display=(deptSel&&deptSel.value==='__other')?'grid':'none';

  /* Contract No. (#1) — synced to BPE header for the client quotation */
  const conSel=root.querySelector('#w1-contract');
  const conOther=root.querySelector('#w1-contract-other');
  const conVal=conSel&&conSel.value==='__other'?(conOther?conOther.value:''):(conSel?conSel.value:'');
  const conRow=root.querySelector('#w1-contract-other-row');
  if(conRow)conRow.style.display=(conSel&&conSel.value==='__other')?'grid':'none';
  const bcon=root.querySelector('#bpecontract');if(bcon)bcon.value=conVal;
  /* Manager (#2) — internal only, never shown on the client sheet */
  const mgrSel=root.querySelector('#w1-mgr');
  const mgrOther=root.querySelector('#w1-mgr-other');
  const mgrVal=mgrSel&&mgrSel.value==='__other'?(mgrOther?mgrOther.value:''):(mgrSel?mgrSel.value:'');
  const mgrRow=root.querySelector('#w1-mgr-other-row');
  if(mgrRow)mgrRow.style.display=(mgrSel&&mgrSel.value==='__other')?'grid':'none';
  window._meta={dept:deptVal,mgr:mgrVal,contract:conVal};
  /* full data linkage across all sheets (#3): WCC1 is the single source of truth */
  const wDesc=root.querySelector('#w2desc');if(wDesc)wDesc.value=((root.querySelector('#w1-desc')||{}).value)||'';
  const wCon=root.querySelector('#w2contract');if(wCon)wCon.value=conVal;
  const wDept=root.querySelector('#w2dept');if(wDept)wDept.value=deptVal;
  const wMgr=root.querySelector('#w2mgr');if(wMgr)wMgr.value=mgrVal;

  const qv=(root.querySelector('#w1-quo')||{}).value||'';
  const wr=root.querySelector('#w2ref');if(wr)wr.value=qv?'WCC1/'+qv:'(linked from WCC1)';

  buildBPE(sub,mu,grand,estSell,dPct/100,dAmt);
  buildW2();updateLump();buildMgr();
}
window.calc=calc;

function buildBPE(sub,mu,grand,estSell,dPctFrac,dAmt){
  const minM=vv('minm')/100;
  let aC=0,bC=0,cC=0,dC=0,aS=0,bS=0,cS=0,dS=0;

  ['A','B','C','D'].forEach(function(sec){
    const rows=root.querySelectorAll('#'+sec+' tr');
    const tb=root.querySelector('#b'+sec);
    let cs=0,ss=0,html='';
    rows.forEach(function(tr,i){
      const q=parseFloat(tr.dataset.q)||0,rr=parseFloat(tr.dataset.cu)||0;
      const c=ct(q*rr),su=ct(rr*(1+mu)),s=ct(q*su);
      cs+=c;ss+=s;
      const d=tr.dataset.d||'(unnamed)',u=tr.dataset.u||'';
      const fSu='Unit Price = CEILING( unit cost '+rr.toFixed(2)+' × (1 + markup '+(mu*100).toFixed(1)+'%), 10 ) = '+fm(su);
      const fS='Total Price = CEILING( qty '+q+' × unit price '+su.toFixed(2)+', 10 ) = '+fm(s);
      if(sec!=='C')html+='<tr><td class="no">'+sec+'1.'+(i+1)+'</td><td>'+d+'</td><td class="c">'+q.toFixed(2)+'</td><td class="c">'+u+'</td><td class="n"><span class="bpef" data-formula="'+fSu+'" title="Double-click to view formula">'+fm(su)+'</span></td><td class="n"><b><span class="bpef" data-formula="'+fS+'" title="Double-click to view formula">'+fm(s)+'</span></b></td></tr>';
    });
    if(sec==='A'){aC=cs;aS=ss;if(tb){tb.innerHTML=html||'<tr><td colspan="6" style="text-align:center;color:#999;padding:8px">No lines</td></tr>';st('bAs',fm(aS));}}
    if(sec==='B'){bC=cs;bS=ss;if(tb){tb.innerHTML=html||'<tr><td colspan="6" style="text-align:center;color:#999;padding:8px">No lines</td></tr>';st('bBs',fm(bS));}}
    if(sec==='C'){cC=cs;cS=ss;st('bCl',fm(cS));st('bCs',fm(cS));}
    if(sec==='D'){dC=cs;dS=ss;if(tb){tb.innerHTML=html||'<tr><td colspan="6" style="text-align:center;color:#999;padding:8px">No lines</td></tr>';st('bDs',fm(dS));}}
  });

  function setF(id,f){const el=byId(id);if(el){el.dataset.formula=f;el.classList.add('bpef');el.title='Double-click to view formula';}}
  setF('bAs','Sub-total A = SUM( all Section A total prices ) = '+fm(aS));
  setF('bBs','Sub-total B = SUM( all Section B total prices ) = '+fm(bS));
  setF('bCl','C selling = SUM over C lines of CEILING( qty × CEILING( unit cost × (1 + markup '+(mu*100).toFixed(1)+'%), 10 ), 10 ) = '+fm(cS));
  setF('bCs','Sub-total C = '+fm(cS));
  setF('bDs','Sub-total D = SUM( all Section D total prices ) = '+fm(dS));

  const tot=aS+bS+cS+dS;st('bpetot',fm(tot));
  const sst=ct(tot*0.08);st('bpesst',fm(sst));
  const grandS=tot+sst;st('bpegrand',fm(grandS));
  setF('bpetot','Total = A '+fm(aS)+' + B '+fm(bS)+' + C '+fm(cS)+' + D '+fm(dS)+' = '+fm(tot));
  setF('bpesst','SST = CEILING( Total '+fm(tot)+' × 8%, 10 ) = '+fm(sst));
  setF('bpegrand','Grand Total = Total '+fm(tot)+' + SST '+fm(sst)+' = '+fm(grandS));

  let finalS=grandS;let discAmt=0,discPct=0,discDesc='';
  if(discOn){
    discPct=vv('bpedpct')/100;discAmt=ct(grandS*discPct);
    discDesc=(root.querySelector('#bpeddesc')||{}).value||'';
    finalS=grandS-discAmt;
    st('bpedamt','- '+fm(discAmt));st('bpegrand2',fm(finalS));
    setF('bpedamt','Discount = CEILING( Grand Total '+fm(grandS)+' × '+(discPct*100).toFixed(2)+'%, 10 ) = '+fm(discAmt));
    setF('bpegrand2','Revised Grand Total = Grand Total '+fm(grandS)+' − Discount '+fm(discAmt)+' = '+fm(finalS));
    const gl=root.querySelector('#bpegl');if(gl)gl.textContent='GRAND TOTAL PRICE - before discount (RM)';
    const wcc1Equiv=ct(dPctFrac*grandS);
    const matches=Math.abs(discAmt-wcc1Equiv)<1;
    const mn=root.querySelector('#bpedmatch');
    if(mn){
      mn.style.background=matches?'var(--green-bg)':'var(--amber-bg)';
      mn.style.color=matches?'var(--green)':'var(--amber)';
      mn.textContent=matches?('Discount '+fm(discAmt)+' matches WCC1 D reserve at '+fp2(dPctFrac)+' of sales = '+fm(wcc1Equiv)+'.'):('Discount '+fm(discAmt)+' ('+fp2(discPct)+' of sales). WCC1 D reserve at same % = '+fm(wcc1Equiv)+'.');
    }
  }else{
    const gl=root.querySelector('#bpegl');if(gl)gl.textContent='GRAND TOTAL PRICE (RM)';
  }

  const margin=finalS-grand;const muPct=grand>0?margin/grand:0;const mPct=finalS>0?margin/finalS:0;
  st('bmcost',fm(grand));st('bmsales',fm(finalS));st('bmmargin',fm(margin));st('bmmu',fp2(muPct)+' / ');
  const mpe=root.querySelector('#bmmp');
  if(mpe){mpe.textContent=fp2(mPct);}
  const mw=root.querySelector('#bpemw');if(mw)mw.className='wcc-mw'+(mPct<minM&&finalS>0?' on':'');

  const bse=root.querySelector('#w2bpes');if(bse)bse.value=fm(finalS);
  window._bD={aC:aC,bC:bC,cC:cC,dC:dC,aS:aS,bS:bS,cS:cS,dS:dS,grand:grand,grandS:grandS,finalS:finalS,margin:margin,muPct:muPct,mPct:mPct,minM:minM,discOn:discOn,discAmt:discAmt,discPct:discPct,discDesc:discDesc};
}

function buildW2(){
  ['A','B','C','D'].forEach(function(sec){
    const rows=root.querySelectorAll('#'+sec+' tr');
    const tb=root.querySelector('#w2'+sec);if(!tb)return;
    const ex=[];tb.querySelectorAll('tr').forEach(function(tr){const a=tr.querySelector('.wai');ex.push(a?a.value:'');});
    let html='';
    rows.forEach(function(tr,i){
      const q=parseFloat(tr.dataset.q)||0,rr=parseFloat(tr.dataset.cu)||0,d=tr.dataset.d||'(unnamed)',u=tr.dataset.u||'';
      const av=ex[i]!==undefined?ex[i]:'';
      const fml=tr.dataset.fover?('Rate — WCC1 custom formula: '+tr.dataset.fover+' = '+fm(rr)):('Rate = WCC1 unit price (MYR) = '+fm(rr));
      html+='<tr data-r="'+rr+'" data-p="'+q+'"><td class="no">'+sec+'1.'+(i+1)+'</td><td>'+d+'</td><td class="c">'+u+'</td>'+
        '<td class="c" style="background:var(--blue-bg);color:#00008B">'+q.toFixed(2)+'</td>'+
        '<td class="c"><input class="wcc-i wai" type="number" min="0" value="'+av+'" oninput="calcW2();rs()" style="width:46px"></td>'+
        '<td class="n"><span class="wcc-fr bpef" data-formula="'+fml.replace(/"/g,'&quot;')+'" title="Double-click to view formula">'+fm(rr)+'</span></td>'+
        '<td class="n"><span class="wcc-ca2 wat bpef" title="Double-click to view formula" style="cursor:help">RM 0.00</span></td><td class="n"><span class="wav wcc-vz bpef" title="Double-click to view formula">-</span></td></tr>';
    });
    tb.innerHTML=html||'<tr><td colspan="8" style="text-align:center;color:#999;padding:8px">No lines in WCC1</td></tr>';
  });
  calcW2();
}

function calcW2(){
  let ga=0,gp=0,gv=0;
  function setF2(id,f){const el=byId(id);if(el){el.dataset.formula=f;el.classList.add('bpef');el.title='Double-click to view formula';}}
  ['A','B','C','D'].forEach(function(sec){
    let sa=0,sp=0,sv=0;
    root.querySelectorAll('#w2'+sec+' tr').forEach(function(tr){
      const r=parseFloat(tr.dataset.r)||0,p=parseFloat(tr.dataset.p)||0;
      const ai=tr.querySelector('.wai');const a=parseFloat(ai&&ai.value)||0;
      const at=ct(a*r),pt=ct(p*r);sa+=at;sp+=pt;
      const te=tr.querySelector('.wat');
      if(te){te.textContent=fm(at);te.dataset.formula='Actual Total = CEILING( actual qty '+a+' × rate '+r.toFixed(2)+', 10 ) = '+fm(at);}
      const ve=tr.querySelector('.wav');
      if(ve){if(a>0){const d=at-pt;sv+=d;ve.textContent=(d>0?'+':'')+fm(d);ve.className='wav bpef '+(d>0?'wcc-vp':d<0?'wcc-vn':'wcc-vz');tr.style.background=d>0?'var(--red-bg)':d<0?'var(--green-bg)':'';
        ve.dataset.formula='Variance = Actual Total '+fm(at)+' − Planned Total CEILING( plan '+p+' × rate '+r.toFixed(2)+', 10 ) '+fm(pt)+' = '+(d>0?'+':'')+fm(d);}
      else{ve.textContent='-';ve.className='wav bpef wcc-vz';tr.style.background='';ve.dataset.formula='Variance = Actual Total − Planned Total (enter an Actual qty first)';}}
    });
    st('2'+sec+'s',fm(sa));
    setF2('2'+sec+'s','Sub-total '+sec+' = SUM( all Section '+sec+' actual totals ) = '+fm(sa));
    const ve=byId('2'+sec+'v');
    if(ve){const any=Array.prototype.some.call(root.querySelectorAll('#w2'+sec+' .wai'),function(x){return parseFloat(x.value)>0;});
      if(any){ve.textContent=(sv>0?'+':'')+fm(sv);ve.className='n bpef '+(sv>0?'wcc-vp':sv<0?'wcc-vn':'wcc-vz');}else{ve.textContent='-';ve.className='n bpef wcc-vz';}
      ve.dataset.formula='Section '+sec+' variance = SUM( actual − planned per line ) = '+(sv>0?'+':'')+fm(sv);ve.title='Double-click to view formula';}
    ga+=sa;gp+=sp;gv+=sv;
  });
  let xt=0;root.querySelectorAll('#w2X tr').forEach(function(tr){
    const i=tr.querySelectorAll('input');const q=parseFloat(i[1]&&i[1].value)||0,r=parseFloat(i[2]&&i[2].value)||0,t=ct(q*r);xt+=t;
    const te=tr.querySelector('.rt');if(te){te.textContent=fm(t);te.dataset.formula='=CEILING( qty '+q+' × rate '+r.toFixed(2)+', 10 ) = '+fm(t);}
  });
  st('2Xs',fm(xt));const xv=byId('2Xv');if(xv){xv.textContent=xt>0?'+'+fm(xt)+' (new)':'-';xv.className='n '+(xt>0?'wcc-vp':'wcc-vz');}
  setF2('2Xs','Sub-total Unplanned = SUM( extra line totals ) = '+fm(xt));
  ga+=xt;gv+=xt;
  st('2tot',fm(ga));const gv2=byId('2var');if(gv2&&ga>0){gv2.textContent=(gv>0?'+':'')+fm(gv);gv2.className='n bpef '+(gv>0?'wcc-vp':gv<0?'wcc-vn':'wcc-vz');}else if(gv2){gv2.textContent='-';gv2.className='n bpef wcc-vz';}
  setF2('2tot','TOTAL ACTUAL COST = Sub-totals A + B + C + D + Unplanned '+fm(xt)+' = '+fm(ga));
  setF2('2var','Total variance = SUM( section variances ) + Unplanned '+fm(xt)+' = '+(gv>0?'+':'')+fm(gv));
  st('2mp',fm(gp));st('2ma',fm(ga));
  if(ga>0){
    const ve2=byId('2mv');if(ve2){ve2.textContent=(gv>0?'+':'')+fm(gv);ve2.className='wcc-mv '+(gv>0?'bad':'good');}
    const vp=gp>0?gv/gp:0;const vpe=byId('2mvp');if(vpe){vpe.textContent=(vp>0?'+':'')+fp2(vp);vpe.className='wcc-mv '+(vp>0?'warn':'good');}
    const bpeTxt=(root.querySelector('#w2bpes')||{}).value||'RM 0';
    const bpeS=parseFloat(bpeTxt.replace(/[^0-9.]/g,''))||0;
    if(bpeS>0){const m=(bpeS-ga)/bpeS;const me=byId('2mam');if(me){me.textContent=fp2(m);me.className='wcc-mv '+(m>=0.3?'good':m>=0.2?'warn':'bad');}}
  }else{['2mv','2mvp','2mam'].forEach(function(id){const e=byId(id);if(e){e.textContent='-';e.className='wcc-mv';}});}
  window._w2={grandAct:ga,grandPlan:gp,grandVar:gv};
}
window.calcW2=calcW2;

function buildMgr(){
  const d=window._bD;if(!d)return;
  st('gC',fm(d.grand));st('gS',fm(d.finalS));st('gM',fm(d.margin));st('gMp',fp2(d.mPct));
  const gMpEl=root.querySelector('#gMp');if(gMpEl)gMpEl.style.color=d.mPct>=d.minM?'#1A7A3C':d.mPct>=d.minM*0.8?'#B8860B':'#C0392B';
  if(d.discOn){
    st('gSs','After '+fp2(d.discPct)+' discount (pre-discount: '+fm(d.grandS)+')');
    const gD=root.querySelector('#gDisc');if(gD)gD.style.display='block';
    st('gDd',d.discDesc||'Discount applied');st('gDa','- '+fm(d.discAmt));
  }else{st('gSs','Quotation to client (incl. SST)');const gD=root.querySelector('#gDisc');if(gD)gD.style.display='none';}
  const gw=root.querySelector('#gW');
  if(gw){gw.className='wcc-mw'+(d.mPct<d.minM&&d.finalS>0?' on':'');gw.textContent='Margin ('+fp2(d.mPct)+') below '+fp2(d.minM)+' threshold - management approval required.';}
  const secs=[{l:'A - Items/Equipment',c:d.aC,s:d.aS},{l:'B - Manpower',c:d.bC,s:d.bS},{l:'C - Mob/Demob',c:d.cC,s:d.cS},{l:'D - Unplanned Item',c:d.dC,s:d.dS}];
  let rows='';
  secs.forEach(function(s){const p=s.s-s.c,pct=s.s>0?p/s.s:0;rows+='<tr><td>'+s.l+'</td><td class="n">'+fm(s.c)+'</td><td class="n">'+fm(s.s)+'</td><td class="n">'+fm(p)+'</td><td class="n">'+fp2(pct)+'</td></tr>';});
  if(d.discOn){rows+='<tr><td>Revision 2 - Discount ('+fp2(d.discPct)+')</td><td class="n">-</td><td class="n" style="color:#C0392B">- '+fm(d.discAmt)+'</td><td class="n" style="color:#C0392B">- '+fm(d.discAmt)+'</td><td class="n">-</td></tr>';}
  rows+='<tr><td>TOTAL'+(d.discOn?' (after discount)':'')+'</td><td class="n">'+fm(d.grand)+'</td><td class="n">'+fm(d.finalS)+'</td><td class="n">'+fm(d.margin)+'</td><td class="n">'+fp2(d.mPct)+'</td></tr>';
  st('gT',rows);root.querySelector('#gT').innerHTML=rows;
  const w2=window._w2;const se=root.querySelector('#gSt');
  if(w2&&w2.grandAct>0){se.textContent='In Execution';se.style.color='#B8860B';}
  else{se.textContent='Pre-Execution';se.style.color='#1F3864';}
  updateRegbook(d);
}

/* ---- Performance charts (#3): every WCC (by Quo No.) is credited to BOTH its department and its manager ---- */
const REG_KEY='costflow_wcc_regbook_v1';
function updateRegbook(d){
  try{
    const quo=((root.querySelector('#w1-quo')||{}).value||'').trim();
    const meta=window._meta||{};
    let book={};try{book=JSON.parse(localStorage.getItem(REG_KEY)||'{}')||{};}catch(e){book={};}
    if(quo&&d&&d.finalS>0){
      book[quo]={dept:(meta.dept||'').trim()||'(no department)',mgr:(meta.mgr||'').trim()||'(no manager)',sell:d.finalS,cost:d.grand,ts:Date.now()};
      try{localStorage.setItem(REG_KEY,JSON.stringify(book));}catch(e){}
    }
    renderCharts(book);
  }catch(e){}
}
function renderCharts(book){
  const agg=function(field){
    const m={};
    Object.keys(book).forEach(function(q){const r=book[q];const k=r[field]||'—';if(!m[k])m[k]={v:0,n:0};m[k].v+=(+r.sell||0);m[k].n++;});
    return Object.keys(m).map(function(k){return{k:k,v:m[k].v,n:m[k].n};}).sort(function(a,b){return b.v-a.v;});
  };
  const draw=function(id,list,color){
    const el=root.querySelector('#'+id);if(!el)return;
    if(!list.length){el.innerHTML='<div style="font-size:9.5px;color:#999;padding:6px 0">No WCCs registered yet — give this WCC a Quo No. and it will appear here.</div>';return;}
    const max=list[0].v||1;let h='';
    list.forEach(function(it){
      const pct=Math.max(4,Math.round(it.v/max*100));
      h+='<div style="margin-bottom:5px"><div style="display:flex;justify-content:space-between;font-size:9.5px;color:#333"><span style="font-weight:700">'+it.k+'</span><span>'+fm(it.v)+' · '+it.n+' WCC'+(it.n>1?'s':'')+'</span></div>'+
        '<div style="background:#eef0f4;border-radius:3px;height:12px;overflow:hidden"><div style="width:'+pct+'%;height:100%;background:'+color+';border-radius:3px"></div></div></div>';
    });
    el.innerHTML=h;
  };
  draw('chDept',agg('dept'),'linear-gradient(90deg,#1F3864,#4a6db3)');
  draw('chMgr',agg('mgr'),'linear-gradient(90deg,#1b6b3a,#4db87a)');
}
function clearRegbook(){
  if(!confirm('Clear the chart history (all registered WCCs) saved in this browser?'))return;
  try{localStorage.removeItem(REG_KEY);}catch(e){}
  renderCharts({});wccToast('Chart history cleared');
}
window.clearRegbook=clearRegbook;

function sbm(skip){
  const mode=(root.querySelector('#bpemode')||{}).value;
  const det=root.querySelector('#bpedet'),lump=root.querySelector('#bpelump');
  if(det)det.style.display=mode==='lump'?'none':'block';
  if(lump)lump.style.display=mode==='lump'?'block':'none';
  if(mode==='lump')buildLumpCards();
  if(!skip)rs();
}
window.sbm=sbm;

function buildLumpCards(){
  const mu=vv('markup')/100||0.45;
  const container=root.querySelector('#lumpcards');if(!container)return;
  const existing={};root.querySelectorAll('#lumpcards [data-li]').forEach(function(e){existing[e.dataset.li]=e.value;});
  const secs=[{key:'A',label:'A - Items / Equipment / Materials'},{key:'B',label:'B - Provision of Manpower'},{key:'C',label:'C - Mobilization & Demobilization'},{key:'D',label:'D - Unplanned Item'}];
  let html='';
  secs.forEach(function(s){
    let cost=0;root.querySelectorAll('#'+s.key+' tr').forEach(function(tr){cost+=parseFloat(tr.dataset.t)||0;});
    const sug=ct(cost*(1+mu));
    const dk='lump-desc-'+s.key,tk='lump-total-'+s.key;
    html+='<div style="border:1px solid #ccc;border-radius:6px;margin-bottom:8px;overflow:hidden">'+
      '<div style="padding:6px 10px;font-size:11px;font-weight:700;color:#fff;background:var(--navy-light)">'+s.label+' <span style="font-weight:400;font-size:9px">suggested: '+fm(sug)+'</span></div>'+
      '<div style="padding:8px 10px">'+
      '<textarea data-li="'+dk+'" style="width:100%;padding:5px;font-size:11px;border:1px solid #ccc;border-radius:3px;min-height:30px;margin-bottom:6px">'+(existing[dk]!==undefined?existing[dk]:'Provision of '+s.label.split('-')[1].trim().toLowerCase()+' as per scope.')+'</textarea>'+
      '<input class="wcc-i wcc-iw" type="number" step="0.01" data-li="'+tk+'" value="'+(existing[tk]!==undefined?existing[tk]:sug.toFixed(2))+'" oninput="updateLump();rs()" style="width:110px;font-weight:700;font-size:13px">'+
      '</div></div>';
  });
  container.innerHTML=html;updateLump();
}

function updateLump(){
  if((root.querySelector('#bpemode')||{}).value!=='lump')return;
  let g=0;root.querySelectorAll('[data-li^="lump-total-"]').forEach(function(e){g+=parseFloat(e.value)||0;});
  st('lumpgt',fm(g));
}
window.updateLump=updateLump;

function addDisc(){
  discOn=true;
  const ds=root.querySelector('#bpedsec'),da=root.querySelector('#bpedadd');
  if(ds)ds.style.display='block';if(da)da.style.display='none';
  const totalDPct=vv('d1')+vv('d2')+vv('d3');
  const dp=root.querySelector('#bpedpct');if(dp)dp.value=totalDPct.toFixed(2);
  calc();rs();
}
function rmDisc(){
  discOn=false;
  const ds=root.querySelector('#bpedsec'),da=root.querySelector('#bpedadd');
  if(ds)ds.style.display='none';if(da)da.style.display='flex';
  calc();rs();
}
window.addDisc=addDisc;window.rmDisc=rmDisc;

let canvasCtx=null,drawing=false;
function openSig(targetId){
  curSig=targetId;upSig=null;
  document.getElementById('sigmo').style.display='flex';
  document.getElementById('mn').value=(root.querySelector('#'+targetId+'-n')||{}).value||'';
  document.getElementById('mt3').value=(root.querySelector('#'+targetId+'-t')||{}).value||'';
  document.getElementById('suph').style.display='block';
  document.getElementById('supreview').style.display='none';
  document.getElementById('sfile').value='';
  switchSigTab('draw');
  setTimeout(initCanvas,40);
}
function switchSigTab(tab){
  document.querySelectorAll('.sigtab').forEach(function(t){
    const on=t.dataset.tab===tab;
    t.classList.toggle('on',on);
    t.style.borderBottomColor=on?'#1F3864':'transparent';
    t.style.color=on?'#1F3864':'#888';
    t.style.fontWeight=on?'700':'400';
  });
  document.getElementById('stdraw').style.display=tab==='draw'?'block':'none';
  document.getElementById('stupload').style.display=tab==='upload'?'block':'none';
}
function closeSigMo(){document.getElementById('sigmo').style.display='none';curSig=null;}
function initCanvas(){
  const c=document.getElementById('sigcanvas');
  const r=c.getBoundingClientRect();c.width=r.width*2;c.height=r.height*2;
  canvasCtx=c.getContext('2d');canvasCtx.scale(2,2);
  canvasCtx.strokeStyle='#1a1a4e';canvasCtx.lineWidth=2;canvasCtx.lineCap='round';
  canvasCtx.clearRect(0,0,c.width,c.height);
  function gp(e){const rr=c.getBoundingClientRect();const t=e.touches?e.touches[0]:e;return{x:t.clientX-rr.left,y:t.clientY-rr.top};}
  function start(e){drawing=true;const p=gp(e);canvasCtx.beginPath();canvasCtx.moveTo(p.x,p.y);e.preventDefault();}
  function move(e){if(!drawing)return;const p=gp(e);canvasCtx.lineTo(p.x,p.y);canvasCtx.stroke();e.preventDefault();}
  function end(){drawing=false;}
  c.onmousedown=start;c.onmousemove=move;c.onmouseup=end;c.onmouseleave=end;
  c.ontouchstart=start;c.ontouchmove=move;c.ontouchend=end;
}
function clrSig(){
  const c=document.getElementById('sigcanvas');if(canvasCtx)canvasCtx.clearRect(0,0,c.width,c.height);
  upSig=null;document.getElementById('suph').style.display='block';document.getElementById('supreview').style.display='none';document.getElementById('sfile').value='';
}
function handleUp(e){
  const file=e.target.files[0];if(!file)return;
  const reader=new FileReader();
  reader.onload=function(ev){upSig=ev.target.result;const p=document.getElementById('supreview');p.src=upSig;p.style.display='block';document.getElementById('suph').style.display='none';};
  reader.readAsDataURL(file);
}
function saveSig(){
  if(!curSig)return;
  const isDraw=document.querySelector('.sigtab.on').dataset.tab==='draw';
  let dataUrl=null;
  if(!isDraw&&upSig){dataUrl=upSig;}
  else if(isDraw){
    const c=document.getElementById('sigcanvas');
    const blank=document.createElement('canvas');blank.width=c.width;blank.height=c.height;
    if(c.toDataURL()===blank.toDataURL()){alert('Please draw a signature or upload an image first.');return;}
    dataUrl=c.toDataURL();
  }else{alert('Please draw a signature or upload an image first.');return;}
  if(typeof curSig==='string'&&curSig.indexOf('__float:')===0){
    addStampImage(curSig.slice(8),dataUrl);closeSigMo();rs();return;
  }
  const target=root.querySelector('#'+curSig);
  SS[curSig]=44;
  target.innerHTML='<img src="'+dataUrl+'" style="height:44px">';
  target.classList.add('signed');
  const newName=document.getElementById('mn').value.trim();
  const newTitle=document.getElementById('mt3').value.trim();
  if(newName){const n=root.querySelector('#'+curSig+'-n');if(n)n.value=newName;}
  if(newTitle){const t=root.querySelector('#'+curSig+'-t');if(t)t.value=newTitle;}
  const q=root.querySelector('#'+curSig+'-q');if(q){q.textContent='Signed';q.classList.add('ok');}
  [curSig+'-sm',curSig+'-sp'].forEach(function(bid){const b=root.querySelector('#'+bid);if(b)b.style.display='inline-block';});
  closeSigMo();rs();
}
function resizeSig(id,delta){
  const el=root.querySelector('#'+id);const img=el&&el.querySelector('img');if(!img)return;
  let s=SS[id]||44;s=Math.max(18,Math.min(80,s+delta*7));SS[id]=s;img.style.height=s+'px';rs();
}

root.addEventListener('click',function(e){
  const t=e.target;
  if(t.classList.contains('wcc-tab')){showTab(t.dataset.t);return;}
  if(t.id==='ub-u'||t.id==='ub-r'){return;}/* handled by direct onclick */
  if(t.classList.contains('wcc-sl')){openSig(t.id);return;}
  if(t.dataset.sig){openSig(t.dataset.sig);return;}
  if(t.dataset.resize){resizeSig(t.dataset.resize,parseInt(t.dataset.d,10));return;}
});

document.getElementById('sigcancel').onclick=closeSigMo;
document.getElementById('sigclear').onclick=clrSig;
document.getElementById('sigsave').onclick=saveSig;
document.getElementById('sfile').onchange=handleUp;
document.getElementById('suarea').onclick=function(){document.getElementById('sfile').click();};
document.querySelectorAll('.sigtab').forEach(function(t){t.onclick=function(){switchSigTab(t.dataset.tab);};});

/* --- Floating, draggable & corner-resizable stamps / signatures (#4) --- */
let floatSeq=0,stampDoc=null;
function floatLayer(doc){return root.querySelector('.wcc-pane[data-p="'+doc+'"] .wcc-doc');}
function makeFloat(doc,src,x,y,w,h){
  const layer=floatLayer(doc);if(!layer)return null;
  const id='flt'+(++floatSeq);
  const d=document.createElement('div');d.className='wcc-float';d.id=id;d.dataset.doc=doc;d.dataset.src=src;d.tabIndex=0;
  d.style.left=(x||40)+'px';d.style.top=(y||40)+'px';d.style.width=(w||150)+'px';d.style.height=(h||90)+'px';
  d.innerHTML='<span class="flbl">drag / arrow keys to move • corner or +/− to resize • Del to remove</span>'+
    '<img src="'+src+'" alt="stamp">'+
    '<button class="fdel" title="Remove" onclick="delFloat(\''+id+'\')">×</button>'+
    '<div class="fres" title="Drag to resize"></div>';
  layer.appendChild(d);return d;
}
function delFloat(id){const d=root.querySelector('#'+id);if(d){d.remove();rs();}}
window.delFloat=delFloat;
function knockoutWhite(img){
  /* returns a PNG dataURL with near-white pixels made transparent */
  try{
    const c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;
    const x=c.getContext('2d');x.drawImage(img,0,0);
    const d=x.getImageData(0,0,c.width,c.height);const p=d.data;
    for(let i=0;i<p.length;i+=4){
      const r=p[i],g=p[i+1],b=p[i+2];
      const mn=Math.min(r,g,b);
      if(mn>=238){p[i+3]=0;}                       /* clearly white -> fully transparent */
      else if(mn>=205){p[i+3]=Math.round(p[i+3]*(238-mn)/33);} /* soft edge feather */
    }
    x.putImageData(d,0,0);
    return c.toDataURL('image/png');
  }catch(e){return null;}
}
function addStampImage(doc,src){
  const img=new Image();
  img.onload=function(){
    const cleaned=knockoutWhite(img)||src;
    const W=150;const h=Math.max(36,Math.round(W*(img.naturalHeight/img.naturalWidth))||90);
    makeFloat(doc,cleaned,40,40,W,h);rs();
  };
  img.onerror=function(){makeFloat(doc,src,40,40,150,90);rs();};
  img.src=src;
}
function uploadStamp(doc){if(poLocked)return;stampDoc=doc;const f=document.getElementById('stampfile');f.value='';f.click();}
window.uploadStamp=uploadStamp;
document.getElementById('stampfile').onchange=function(e){
  const file=e.target.files[0];if(!file||!stampDoc)return;const doc=stampDoc;
  const rd=new FileReader();rd.onload=function(ev){addStampImage(doc,ev.target.result);};rd.readAsDataURL(file);
};
function drawFloat(doc){
  if(poLocked)return;
  curSig='__float:'+doc;upSig=null;
  document.getElementById('sigmo').style.display='flex';
  document.getElementById('mn').value='';document.getElementById('mt3').value='';
  document.getElementById('suph').style.display='block';
  document.getElementById('supreview').style.display='none';
  document.getElementById('sfile').value='';
  switchSigTab('draw');setTimeout(initCanvas,40);
}
window.drawFloat=drawFloat;
(function floatDrag(){
  let act=null,raf=null,pend=null;
  function selectOnly(fl){root.querySelectorAll('.wcc-float.sel').forEach(function(x){x.classList.remove('sel');});if(fl)fl.classList.add('sel');}
  function onDown(e){
    if(poLocked)return;
    const fl=e.target.closest&&e.target.closest('.wcc-float');if(!fl){return;}
    if(e.target.classList.contains('fdel'))return;
    const pt=e.touches?e.touches[0]:e;
    const isRes=e.target.classList.contains('fres');
    act={mode:isRes?'resize':'move',el:fl,sx:pt.clientX,sy:pt.clientY,
      ox:parseFloat(fl.style.left)||0,oy:parseFloat(fl.style.top)||0,ow:fl.offsetWidth,oh:fl.offsetHeight};
    selectOnly(fl);fl.focus&&fl.focus();e.preventDefault();
  }
  function flush(){
    raf=null;if(!act||!pend)return;
    if(act.mode==='move'){act.el.style.left=Math.max(0,pend.x)+'px';act.el.style.top=Math.max(0,pend.y)+'px';}
    else{const w=Math.max(30,pend.w);const ratio=act.oh/act.ow||0.6;act.el.style.width=w+'px';act.el.style.height=Math.max(20,Math.round(w*ratio))+'px';}
  }
  function onMove(e){
    if(!act)return;const pt=e.touches?e.touches[0]:e;
    const dx=pt.clientX-act.sx,dy=pt.clientY-act.sy;
    pend=(act.mode==='move')?{x:act.ox+dx,y:act.oy+dy}:{w:act.ow+dx};
    if(!raf)raf=requestAnimationFrame(flush);
    if(e.cancelable)e.preventDefault();
  }
  function onUp(){if(act){act=null;pend=null;if(raf){cancelAnimationFrame(raf);raf=null;}rs();}}
  root.addEventListener('mousedown',onDown);
  root.addEventListener('touchstart',onDown,{passive:false});
  document.addEventListener('mousemove',onMove,{passive:false});
  document.addEventListener('touchmove',onMove,{passive:false});
  document.addEventListener('mouseup',onUp);
  document.addEventListener('touchend',onUp);
  /* keyboard control of the selected stamp/signature */
  document.addEventListener('keydown',function(e){
    if(poLocked)return;
    const fl=root.querySelector('.wcc-float.sel');if(!fl)return;
    const tag=(e.target.tagName||'').toLowerCase();
    if(tag==='input'||tag==='textarea'||tag==='select')return; /* don't hijack typing */
    const step=e.shiftKey?12:2;let used=true;
    const L=parseFloat(fl.style.left)||0,T=parseFloat(fl.style.top)||0,W=fl.offsetWidth,H=fl.offsetHeight;
    switch(e.key){
      case 'ArrowLeft':fl.style.left=Math.max(0,L-step)+'px';break;
      case 'ArrowRight':fl.style.left=(L+step)+'px';break;
      case 'ArrowUp':fl.style.top=Math.max(0,T-step)+'px';break;
      case 'ArrowDown':fl.style.top=(T+step)+'px';break;
      case '+':case '=':{const nw=W+step*2;fl.style.width=nw+'px';fl.style.height=Math.round(nw*(H/W))+'px';break;}
      case '-':case '_':{const nw=Math.max(30,W-step*2);fl.style.width=nw+'px';fl.style.height=Math.round(nw*(H/W))+'px';break;}
      case 'Delete':case 'Backspace':delFloat(fl.id);return;
      case 'Escape':fl.classList.remove('sel');return;
      default:used=false;
    }
    if(used){e.preventDefault();rs();}
  });
  /* click empty area deselects */
  root.addEventListener('mousedown',function(e){if(!(e.target.closest&&e.target.closest('.wcc-float')))selectOnly(null);},true);
})();

/* --- Calendar helper (#3): open native date picker on click/focus --- */
function dpick(el){try{if(el&&el.showPicker)el.showPicker();}catch(e){}}
window.dpick=dpick;

/* --- Auto-grow wrapping description boxes (#2) --- */
function grow(ta){if(!ta||ta.tagName!=='TEXTAREA')return;ta.style.height='auto';ta.style.height=Math.max(ta.scrollHeight,18)+'px';}
window.grow=grow;
function growAll(){root.querySelectorAll('textarea.wcc-it').forEach(grow);}
window.growAll=growAll;

/* --- Dual-currency conversion (#1/V13): the rate is part of the calculation ---
   Column 1 "Unit Price" = price as entered (in the FROM currency, e.g. supplier USD).
   Column 2 "Unit Price" = entered x rate, in the TO currency (e.g. MYR). Totals and the
   BPE/WCC2 sheets use the converted (TO) value only. Changing from/to/rate recalculates
   live. No internet is used — the rate is typed manually. */
const CUR_SYM={MYR:'RM',USD:'$',EUR:'€',GBP:'£',SGD:'S$',AUD:'A$',JPY:'¥',CNY:'¥',INR:'₹',THB:'฿',IDR:'Rp',AED:'AED '};
function symFor(c){c=(c||'').trim().toUpperCase();return CUR_SYM[c]||(c?c+' ':'');}
function setStatus(msg,col){const s=root.querySelector('#curStatus');if(s){s.textContent=msg;s.style.color=col||'#667';}}
function readCUR(){
  CUR.from=((root.querySelector('#curFrom')||{}).value||'MYR').trim().toUpperCase()||'MYR';
  CUR.to=((root.querySelector('#curTo')||{}).value||'MYR').trim().toUpperCase()||'MYR';
  const r=parseFloat((root.querySelector('#curRate')||{}).value);
  CUR.rate=(r&&r>0)?r:1;
  CUR.symFrom=symFor(CUR.from);CUR.symTo=symFor(CUR.to);
}
/* live handler: rate/currency typed -> recalc immediately */
function applyCurrency(){
  if(poLocked)return;
  readCUR();
  root.querySelectorAll('.curcode-from').forEach(function(i){i.value=CUR.from;});
  root.querySelectorAll('.curcode-to').forEach(function(i){i.value=CUR.to;});
  root.querySelectorAll('.curcode').forEach(function(i){i.value=CUR.to;});
  root.querySelectorAll('#A tr,#B tr,#C tr,#D tr').forEach(function(tr){
    const to=tr.querySelector('.pto'),from=tr.querySelector('.pfrom');
    if(to&&from){const r=CUR.rate||1;from.value=r?(cnum(to.value)/r).toFixed(2):'0';}
  });
  if(CUR.from===CUR.to)setStatus('no conversion (1 '+CUR.from+' = '+CUR.rate+' '+CUR.to+')','#667');
  else setStatus('1 '+CUR.from+' = '+CUR.rate+' '+CUR.to+'  → column 2 & totals in '+CUR.to,'#1a7a3c');
  calc();rs();
}
window.applyCurrency=applyCurrency;
function curHint(){applyCurrency();}
window.curHint=curHint;
function resetCurrency(){
  if(poLocked)return;
  const f=root.querySelector('#curFrom');if(f)f.value='MYR';
  const t=root.querySelector('#curTo');if(t)t.value='MYR';
  const ri=root.querySelector('#curRate');if(ri)ri.value=1;
  applyCurrency();
  setStatus('reset to MYR (1:1)','#667');
}
window.resetCurrency=resetCurrency;

/* ---------- Import from the COSTFLOW Excel template ---------- */
function importXlsx(){
  if(typeof XLSX==='undefined'){alert('The Excel reader could not load. Please make sure you are online (it loads a small library), then try again.');return;}
  const f=document.getElementById('xlsxfile');f.value='';f.click();
}
window.importXlsx=importXlsx;
function norm(v){return String(v==null?'':v).trim().toLowerCase().replace(/[^a-z0-9]/g,'');}
function setVal(id,val){const el=root.querySelector('#'+id);if(el&&val!=null&&val!==''){el.value=val;}}
function xlsxDate(v){
  if(v==null||v==='')return '';
  if(v instanceof Date){const p=n=>String(n).padStart(2,'0');return v.getFullYear()+'-'+p(v.getMonth()+1)+'-'+p(v.getDate());}
  const s=String(v).trim();
  let m=s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);if(m)return m[1]+'-'+String(m[2]).padStart(2,'0')+'-'+String(m[3]).padStart(2,'0');
  m=s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})/);if(m)return m[3]+'-'+String(m[2]).padStart(2,'0')+'-'+String(m[1]).padStart(2,'0');
  return s;
}
function applySettings(rows){
  const rate=()=>cnum((root.querySelector('#curRate')||{}).value)||1;
  rows.forEach(function(r){
    const k=norm(r[0]);const v=r[1];if(!k)return;
    if(k==='jobtitle'||k==='projecttitle')setVal('w1-desc',v);
    else if(k==='contractno'||k==='contract'){
      const sel=root.querySelector('#w1-contract');if(sel){
        const opt=Array.prototype.find.call(sel.options,function(o){return norm(o.value)===norm(v)||norm(o.textContent)===norm(v);});
        if(opt&&opt.value!=='__other'){sel.value=opt.value;}
        else{sel.value='__other';setVal('w1-contract-other',v);}
      }
    }
    else if(k==='manager'||k==='mgr'){
      const sel=root.querySelector('#w1-mgr');if(sel){
        const opt=Array.prototype.find.call(sel.options,function(o){return norm(o.value)===norm(v)||norm(o.textContent)===norm(v);});
        if(opt&&opt.value!=='__other'){sel.value=opt.value;}
        else{sel.value='__other';setVal('w1-mgr-other',v);}
      }
    }
    else if(k==='clientname'||k==='client')setVal('w1-client',v);
    else if(k==='quotationno'||k==='quono'||k==='quotation')setVal('w1-quo',v);
    else if(k==='date')setVal('w1-date',xlsxDate(v));
    else if(k==='department'||k==='dept'){
      const sel=root.querySelector('#w1-dept');if(sel){
        const opt=Array.prototype.find.call(sel.options,function(o){return norm(o.value)===norm(v)||norm(o.textContent)===norm(v);});
        if(opt){sel.value=opt.value;}
        else{const oo=Array.prototype.find.call(sel.options,function(o){return o.value==='__other';});if(oo){sel.value='__other';const otherRow=root.querySelector('#w1-dept-other-row');if(otherRow)otherRow.style.display='grid';setVal('w1-dept-other',v);}}
      }
    }
    else if(k==='currencyfrom'||k==='from')setVal('curFrom',String(v).toUpperCase());
    else if(k==='currencyto'||k==='to')setVal('curTo',String(v).toUpperCase());
    else if(k.indexOf('rate')===0)setVal('curRate',v);
    else if(k.indexOf('markup')===0)setVal('markup',v);
    else if(k==='discounttier1'||k==='discount1'||k==='discounttier1pct')setVal('d1',v);
    else if(k==='discounttier2'||k==='discount2')setVal('d2',v);
    else if(k==='discounttier3'||k==='discount3')setVal('d3',v);
    else if(k.indexOf('contingency')===0)setVal('e1',v);
    else if(k.indexOf('standby')===0)setVal('standbyPct',v);
  });
}
function fillRow(tr,it,isB){
  const r=cnum((root.querySelector('#curRate')||{}).value)||1;
  const pm=(it.pm===''||it.pm==null)?null:cnum(it.pm);
  const pf=(it.pf===''||it.pf==null)?null:cnum(it.pf);
  const set=(sel,val)=>{const e=tr.querySelector(sel);if(e&&val!=null){e.value=val;}};
  if(isB){
    const desc=String(it.desc==null?'':it.desc).trim();
    const role=tr.querySelector('.mprole'),nm=tr.querySelector('.mpname');
    if(role){
      const opt=Array.prototype.find.call(role.options,function(o){return o.value===desc;});
      if(opt&&desc){role.value=desc;if(nm){nm.style.display='none';nm.value=desc;}}
      else{role.value='__other';if(nm){nm.style.display='block';nm.value=desc;}}
    } else if(nm){nm.value=desc;}
    set('.mpqty',it.qty);set('.mpuom',it.uom||'days');
  }
  else{set('.dsc',it.desc);set('.qty',it.qty);set('.uomx',it.uom||'lot');}
  let toV=pm, fromV=pf;
  if(toV==null&&fromV!=null)toV=Math.round(fromV*r*100)/100;
  if(fromV==null&&toV!=null)fromV=r?Math.round(toV/r*100)/100:0;
  set('.pto',toV==null?'':toV);set('.pfrom',fromV==null?'':fromV);
  set('.rmk',it.rmk||'');
  const ta=tr.querySelector('.dsc,.mpname');if(ta&&ta.tagName==='TEXTAREA'&&window.grow)grow(ta);
}
function applyWCC1(rows){
  // find header row
  let hi=-1,col={};
  for(let i=0;i<rows.length;i++){
    const r=rows[i].map(norm);
    if(r.indexOf('section')>=0){hi=i;rows[i].forEach(function(h,j){col[norm(h)]=j;});break;}
  }
  if(hi<0)return 0;
  const cS=col['section'], cD=col['descriptionrole']!=null?col['descriptionrole']:(col['description']!=null?col['description']:2);
  const cQ=col['qty'], cU=col['uom'], cPF=(col['unitpricefrom']!=null?col['unitpricefrom']:col['ratefrom']);
  const cPM=(col['unitpricemyr']!=null?col['unitpricemyr']:(col['ratemyr']!=null?col['ratemyr']:col['unitpriceto']));
  const cR=(col['remarks']!=null?col['remarks']:col['remark']);
  const buckets={A:[],B:[],C:[],D:[]};
  for(let i=hi+1;i<rows.length;i++){
    const row=rows[i];if(!row)continue;
    const sec=String(row[cS]==null?'':row[cS]).trim().toUpperCase();
    if(!buckets[sec])continue;
    const desc=cD!=null?row[cD]:'';
    const qty=cQ!=null?row[cQ]:'';
    const hasData=(desc!=null&&String(desc).trim()!=='')||(qty!==''&&qty!=null);
    if(!hasData)continue;
    buckets[sec].push({desc:desc,qty:qty,uom:cU!=null?row[cU]:'',pf:cPF!=null?row[cPF]:'',pm:cPM!=null?row[cPM]:'',rmk:cR!=null?row[cR]:''});
  }
  let n=0;
  ['A','B','C','D'].forEach(function(sec){
    const tb=root.querySelector('#'+sec);if(!tb)return;
    tb.innerHTML='';RC[sec]=0;
    const items=buckets[sec];
    if(!items.length){ if(sec==='B')addMP(); else addRow(sec); return; }
    items.forEach(function(it){
      if(sec==='B')addMP(); else addRow(sec);
      const tr=tb.lastElementChild;
      fillRow(tr,it,sec==='B');
      n++;
    });
  });
  return n;
}
document.getElementById('xlsxfile').onchange=function(e){
  const file=e.target.files[0];if(!file)return;
  const rd=new FileReader();
  rd.onload=function(ev){
    try{
      const wb=XLSX.read(new Uint8Array(ev.target.result),{type:'array',cellDates:true});
      const get=function(name){const k=Object.keys(wb.Sheets).find(function(s){return norm(s)===norm(name)||norm(s).indexOf(norm(name))===0;});return k?XLSX.utils.sheet_to_json(wb.Sheets[k],{header:1,defval:''}):null;};
      const setRows=get('Settings');const w1Rows=get('WCC1');
      if(setRows)applySettings(setRows);
      let cnt=0;
      if(w1Rows)cnt=applyWCC1(w1Rows);
      applyCurrency();calc();rs();growAll();
      alert('Imported successfully'+(cnt?(': '+cnt+' WCC1 line item'+(cnt>1?'s':'')):'')+'.\nReview WCC1, then open BPE Price / WCC2 in the system.');
    }catch(err){
      alert('Could not read this file. Please use the COSTFLOW Excel template and keep the sheet names (Settings, WCC1).\n\n'+err);
    }
  };
  rd.readAsArrayBuffer(file);
};

/* --- Purchase Order confirmation + lock (#10) --- */
let poLocked=false;
function openPO(){if(poLocked)return;document.getElementById('pomo').style.display='flex';}
function closePO(){document.getElementById('pomo').style.display='none';}
function confirmPO(){
  poLocked=true;closePO();
  ['w1','bpe'].forEach(function(p){
    const pane=root.querySelector('.wcc-pane[data-p="'+p+'"]');if(!pane)return;
    pane.classList.add('wcc-locked');
    pane.querySelectorAll('input,select,textarea,button').forEach(function(el){
      if(el.id==='poBtn')return;
      el.disabled=true;
    });
  });
  const btn=document.getElementById('poBtn');if(btn){btn.disabled=true;btn.textContent='PURCHASE ORDER CONFIRMED ✔';}
  const banner=document.getElementById('poBanner');if(banner)banner.classList.add('on');
}
window.openPO=openPO;window.closePO=closePO;window.confirmPO=confirmPO;

/* --- Excel-like spreadsheet column & row sizing (#6) ---
   Columns: each data table gets a <colgroup>; widths live on <col> so the
   WHOLE column shares one width and new rows inherit it automatically.
   Rows: height set on <tr> (native shared height across all cells in the row).
   Handles appear only on header cells (columns) and the row-header gutter cell. */
(function setupGrid(){
  const EDGE=6;
  /* Build a colgroup for one data table (only when visible, so widths measure correctly) */
  function gridify(tbl){
    if(tbl.dataset.grid==='1')return true;
    const headRow=tbl.querySelector('thead tr');
    if(!headRow)return false;                 /* sub-total tables have no thead: skip */
    if(tbl.offsetParent===null)return false;  /* hidden: defer until its tab is shown */
    const ths=headRow.children, ws=[];
    for(let i=0;i<ths.length;i++)ws.push(Math.round(ths[i].getBoundingClientRect().width)||40);
    const cg=document.createElement('colgroup');
    for(let i=0;i<ths.length;i++){const c=document.createElement('col');c.style.width=ws[i]+'px';cg.appendChild(c);}
    tbl.insertBefore(cg,tbl.firstChild);
    tbl.classList.add('wcc-grid');
    tbl.dataset.grid='1';
    return true;
  }
  function w1Tables(){
    const out=[];const th=root.querySelector('#w1TopHead');if(th)out.push(th);
    ['A','B','C','D'].forEach(function(id){const tb=root.querySelector('#'+id);if(tb){const t=tb.closest('table.wcc-t');if(t)out.push(t);}});
    return out;
  }
  function unifyHeaders(){
    const tabs=w1Tables();if(tabs.length<2)return;
    tabs.forEach(gridify);
    const master=tabs[0].querySelector('colgroup');
    if(master){
      const ws=Array.prototype.map.call(master.children,function(c){return c.style.width;});
      for(let k=1;k<tabs.length;k++){const cg=tabs[k].querySelector('colgroup');if(cg)Array.prototype.forEach.call(cg.children,function(c,i){if(ws[i])c.style.width=ws[i];});}
    }
    for(let k=1;k<tabs.length;k++){const hd=tabs[k].querySelector('thead');if(hd)hd.style.display='none';}
  }
  window.__unifyHeaders=unifyHeaders;
  function gridifyVisible(){root.querySelectorAll('table.wcc-t').forEach(gridify);unifyHeaders();if(window.growAll)growAll();}
  window.__gridifyVisible=gridifyVisible;

  let mode=null,sx=0,sy=0,sw=0,sh=0,colEl=null,rowEl=null,colIdx=-1,resizingW1=false;

  root.addEventListener('mousedown',function(e){
    if(poLocked)return;
    /* Column resize: right edge of a header cell */
    const th=e.target.closest('table.wcc-t thead th');
    if(th){
      const r=th.getBoundingClientRect();
      if(r.right-e.clientX<=EDGE){
        const tbl=th.closest('table.wcc-t');
        if(!gridify(tbl))return;
        const idx=Array.prototype.indexOf.call(th.parentElement.children,th);
        const cols=tbl.querySelectorAll('colgroup col');
        colEl=cols[idx];if(!colEl)return;
        colIdx=idx;resizingW1=(w1Tables().indexOf(tbl)>=0);
        mode='col';sx=e.clientX;sw=parseFloat(colEl.style.width)||r.width;
        document.body.classList.add('wcc-resizing');e.preventDefault();return;
      }
    }
    /* Row resize: bottom edge of the FIRST cell in a body row (the row-header gutter) */
    const td=e.target.closest('table.wcc-t tbody td');
    if(td && td.cellIndex===0){
      const tr=td.parentElement,r=tr.getBoundingClientRect();
      if(r.bottom-e.clientY<=EDGE){
        mode='row';rowEl=tr;sy=e.clientY;sh=r.height;
        document.body.classList.add('wcc-resizing-row');e.preventDefault();return;
      }
    }
  });

  /* Cursor hints: col-resize on header right edge, row-resize on first-cell bottom edge */
  root.addEventListener('mousemove',function(e){
    if(mode)return;
    const th=e.target.closest('table.wcc-t thead th');
    if(th){const r=th.getBoundingClientRect();th.style.cursor=(r.right-e.clientX<=EDGE)?'col-resize':'';return;}
    const td=e.target.closest('table.wcc-t tbody td');
    if(td){const r=td.getBoundingClientRect();td.style.cursor=(td.cellIndex===0&&r.bottom-e.clientY<=EDGE)?'row-resize':'';}
  });

  document.addEventListener('mousemove',function(e){
    if(!mode)return;
    if(mode==='col'){const w=Math.max(28,sw+(e.clientX-sx)/(window.__wccZoom||1));colEl.style.width=w+'px';
      if(resizingW1&&colIdx>=0){w1Tables().forEach(function(t){const c=t.querySelectorAll('colgroup col')[colIdx];if(c)c.style.width=w+'px';});}
      if(window.growAll)growAll();}
    else{const h=Math.max(20,sh+(e.clientY-sy)/(window.__wccZoom||1));rowEl.style.height=h+'px';}
  });
  document.addEventListener('mouseup',function(){
    mode=null;colEl=null;rowEl=null;
    document.body.classList.remove('wcc-resizing','wcc-resizing-row');
  });

  gridifyVisible();
})();

/* --- Undo/Redo: track ANY change + keyboard Ctrl/Cmd+Z / +Y / +Shift+Z (#4) --- */
(function setupUndo(){
  let t;
  function flush(){if(restoring)return;try{calc();}catch(e){console.error('calc failed:',e);}rs();}
  root.addEventListener('input',function(e){if(restoring)return;if(e.target&&e.target.tagName==='TEXTAREA'&&e.target.classList.contains('wcc-it'))grow(e.target);clearTimeout(t);t=setTimeout(flush,200);});
  root.addEventListener('dblclick',function(e){
    const el=e.target;
    if(!el)return;
    if(el.tagName==='INPUT'&&(el.classList.contains('qty')||el.classList.contains('mpqty')||el.classList.contains('rmk'))){fpop(e,el);return;}
    const fv=el.closest?el.closest('.bpef'):null;
    if(fv&&fv.dataset&&fv.dataset.formula&&fv.closest('.wcc-pane[data-p="bpe"],.wcc-pane[data-p="w2"]')){fpop(e,fv);}
  });
  /* ---- Excel-like click-to-reference (active while a formula editor is open) ---- */
  function refCellOf(t){
    if(!t||!t.closest)return null;
    return t.closest('#A .qty,#A .pto,#A .pfrom,#A .rt,#B .mpqty,#B .pto,#B .pfrom,#B .rt,#C .qty,#C .pto,#C .pfrom,#C .rt,#D .qty,#D .pto,#D .pfrom,#D .rt,#Asub,#Bsub,#Csub,#Dsub');
  }
  function refToken(cell){
    if(/^[ABCD]sub$/.test(cell.id||''))return cell.id[0].toUpperCase()+'SUB';
    const tr=cell.closest('tr');const tb=tr&&tr.parentElement;if(!tb||!tb.id)return null;
    const idx=Array.prototype.indexOf.call(tb.children,tr)+1;
    let f='';
    if(cell.classList.contains('qty')||cell.classList.contains('mpqty'))f='Q';
    else if(cell.classList.contains('pto'))f='P';
    else if(cell.classList.contains('pfrom'))f='F';
    else if(cell.classList.contains('rt'))f='T';
    return f?(tb.id+idx+f):null;
  }
  function refInsert(ev){
    const fp=window.__fpopInput;if(!fp||!fp.inp)return false;
    if(ev.target.closest&&ev.target.closest('.wcc-fp'))return false;   /* clicks inside the popup behave normally */
    const cell=refCellOf(ev.target);if(!cell)return false;
    ev.preventDefault();ev.stopPropagation();
    if(ev.type==='mousedown'){
      const tok=refToken(cell);if(!tok)return true;
      const inp=fp.inp;
      const s=inp.selectionStart==null?inp.value.length:inp.selectionStart;
      const e2=inp.selectionEnd==null?s:inp.selectionEnd;
      inp.value=inp.value.slice(0,s)+tok+inp.value.slice(e2);
      const np=s+tok.length;
      inp.focus();try{inp.setSelectionRange(np,np);}catch(x){}
      cell.classList.add('wcc-refflash');setTimeout(function(){cell.classList.remove('wcc-refflash');},450);
    }
    return true;
  }
  document.addEventListener('mousedown',refInsert,true);
  document.addEventListener('click',refInsert,true);
  document.addEventListener('dblclick',function(ev){if(window.__fpopInput&&refCellOf(ev.target)&&!(ev.target.closest&&ev.target.closest('.wcc-fp'))){ev.preventDefault();ev.stopPropagation();}},true);
  root.addEventListener('change',function(){if(restoring)return;clearTimeout(t);flush();});
  document.addEventListener('keydown',function(e){
    const k=(e.key||'').toLowerCase();
    if((e.ctrlKey||e.metaKey)&&k==='z'&&!e.shiftKey){e.preventDefault();doUndo();}
    else if((e.ctrlKey||e.metaKey)&&(k==='y'||(k==='z'&&e.shiftKey))){e.preventDefault();doRedo();}
  });
})();

bsg();calc();
(function restoreSaved(){
  const saved=loadLS();
  if(saved){
    try{rest(saved);growAll();
      const s=root.querySelector('#saveStat');if(s){s.textContent='💾 restored';setTimeout(function(){s.textContent='💾 auto-saved';},1500);}
    }catch(e){/* structure mismatch or corrupt: ignore and keep blank template */}
  }
})();
rs();growAll();
try{applyCurrency();}catch(e){}
/* establish a single clean baseline snapshot AFTER all init mutations */
hist=[];hi=-1;try{rs();}catch(e){}ubtn();
})();
