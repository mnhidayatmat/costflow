/* ============================================================
   COSTFLOW — WCC workspace glue.

   The spreadsheet engine (wcc-engine.js) owns the template and knows
   nothing about the server. This file is the only bridge between them:

     · restores a record's snapshot from the server on load
     · reads the header + totals back out of the sheet on save
     · POSTs / PUTs the record, snapshot included
     · drives the zoom, pan, minimize and full-screen chrome

   It relies on these engine exports: __wccCap, __wccRest, rs, growAll,
   __gridifyVisible.
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.COSTFLOW_WCC || {};
  var ENGINE_LS_KEY = 'costflow_wcc_state_v7';
  var ZOOM_KEY = 'costflow_wcc_zoom';

  var $ = function (sel) { return document.querySelector(sel); };
  var $$ = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };

  function toast(msg) {
    if (window.cfToast) return window.cfToast(msg);
    if (window.wccToast) return window.wccToast(msg);
    console.log(msg);
  }

  /* ----------------------------------------------------------
     Restore. On a fresh open the server's snapshot is what we want. If the
     engine just rehydrated a local draft for this same sheet, that draft holds
     edits made since the last Save — newer than the server — so leave it be.
     ---------------------------------------------------------- */
  (function restoreFromServer() {
    if (!CFG.snapshot || !window.__wccRest || CFG.hasLocalDraft) return;

    try {
      window.__wccRest(CFG.snapshot);
      if (window.rs) window.rs();
      if (window.growAll) window.growAll();
    } catch (e) {
      console.error('Could not restore snapshot:', e);
      toast('Could not restore this record’s template — showing a blank sheet.');
    }
  })();

  /* An approved (or under-review) record must not be edited in place. */
  if (CFG.readonly) {
    $$('#wcc-root input, #wcc-root textarea, #wcc-root select').forEach(function (el) {
      el.readOnly = true;
      el.disabled = el.tagName === 'SELECT';
    });
    $$('#wcc-root button.wcc-ab, #wcc-root button.wcc-db').forEach(function (b) { b.style.display = 'none'; });
  }

  /* ----------------------------------------------------------
     Read the header fields and grand totals back out of the sheet.
     Mirrors readTemplateMeta() from the original prototype.
     ---------------------------------------------------------- */
  function text(id) {
    var el = document.getElementById(id);
    if (!el) return '';
    return el.value !== undefined ? el.value : el.textContent;
  }

  function parseRM(value) {
    return parseFloat(String(value || '').replace(/[^\d.\-]/g, '')) || 0;
  }

  function readTemplateMeta() {
    var dept = text('w1-dept');
    if (dept === '__other') dept = text('w1-dept-other') || 'Other';

    var manager = text('w1-mgr');
    if (manager === '__other') manager = text('w1-mgr-other') || '';

    /* The revised (post-discount) grand total wins when Revision 2 is showing. */
    var revised = document.getElementById('bpegrand2');
    var selling = parseRM(revised && revised.offsetParent !== null ? revised.textContent : '');
    if (!selling) selling = parseRM(text('bpegrand'));

    return {
      quo_no: text('w1-quo') || '(no quo no.)',
      client: text('w1-client') || '(no client)',
      title: text('w1-desc') || '(untitled project)',
      dept: dept || null,
      manager: (manager || '').trim().toUpperCase() || null,
      planned_cost: parseRM(text('w1grand')),
      selling: selling,
      actual: parseRM(text('2tot'))
    };
  }

  /* ==========================================================
     Signatures and stamps

     The engine writes uploaded and hand-drawn images straight into the DOM as
     base64 data URIs, and cap() then folds them into the snapshot. Two of them
     push a save past PHP's post_max_size, where the body is discarded before
     Laravel runs — the browser gets a 200 and the user believes it saved.

     So before every capture we lift each data URI out to a real file and swap
     the URL back into the DOM. The snapshot stays small, the localStorage
     draft stops blowing its quota, and re-saving never re-uploads.
     ========================================================== */

  var MAX_EDGE = 1400;   // plenty for a stamp printed at ~150px
  var MAX_BYTES = 2 * 1024 * 1024;

  /** data: URI -> Blob, without a round trip through fetch(). */
  function dataUriToBlob(uri) {
    var parts = uri.split(',');
    var mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/png';
    var binary = atob(parts[1]);
    var bytes = new Uint8Array(binary.length);

    for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return new Blob([bytes], { type: mime });
  }

  /**
   * Shrink anything larger than MAX_EDGE. A phone photo of a company stamp is
   * several megabytes; at print size it needs a fraction of that.
   * Resolves to a Blob — PNG, so hand-drawn signatures keep their transparency.
   */
  function downscale(uri) {
    return new Promise(function (resolve, reject) {
      var img = new Image();

      img.onload = function () {
        var scale = Math.min(1, MAX_EDGE / Math.max(img.width, img.height));

        if (scale === 1 && dataUriToBlob(uri).size <= MAX_BYTES) {
          resolve(dataUriToBlob(uri));
          return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * scale);
        canvas.height = Math.round(img.height * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
          blob ? resolve(blob) : reject(new Error('Could not process the image.'));
        }, 'image/png');
      };

      img.onerror = function () { reject(new Error('That image could not be read.')); };
      img.src = uri;
    });
  }

  function uploadImage(blob) {
    if (blob.size > MAX_BYTES) {
      return Promise.reject(new Error('An image is too large even after resizing. Use a smaller stamp.'));
    }

    var form = new FormData();
    form.append('file', blob, 'signature.png');

    return fetch(CFG.attachmentUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (window.COSTFLOW && window.COSTFLOW.csrf) || '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: form
    }).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw new Error((body.errors && body.errors.file && body.errors.file[0]) || 'Upload failed.');
        return body.url;
      });
    });
  }

  /**
   * Replace every data: URI in the sheet with a hosted URL.
   * Identical images share a hash server-side, so this uploads each one once.
   */
  function hoistImages() {
    var targets = [];

    document.querySelectorAll('.wcc-sl img[src^="data:"]').forEach(function (img) {
      targets.push({ uri: img.src, apply: function (url) { img.src = url; } });
    });

    document.querySelectorAll('.wcc-float[data-src^="data:"]').forEach(function (float) {
      targets.push({
        uri: float.dataset.src,
        apply: function (url) {
          float.dataset.src = url;
          var img = float.querySelector('img');
          if (img) img.src = url;
        }
      });
    });

    if (!targets.length) return Promise.resolve();

    toast('Uploading ' + targets.length + ' image' + (targets.length > 1 ? 's' : '') + '…');

    var cache = {};

    return Promise.all(targets.map(function (target) {
      cache[target.uri] = cache[target.uri] || downscale(target.uri).then(uploadImage);

      return cache[target.uri].then(target.apply);
    }));
  }

  /*
   * Hoist the moment an image lands, not at save time. The engine writes the
   * data URI into the DOM and its autosave immediately copies it into the
   * localStorage draft — a couple of stamps and that write throws QuotaExceeded.
   * Uploading on sight keeps both the draft and the snapshot small.
   */
  if (!CFG.readonly) {
    var hoisting = false;
    var pending = null;

    var sweep = function () {
      if (hoisting) return;
      if (!document.querySelector('.wcc-sl img[src^="data:"], .wcc-float[data-src^="data:"]')) return;

      hoisting = true;

      hoistImages()
        .then(function () { if (window.rs) window.rs(); })
        .catch(function (err) { toast('⚠ ' + describeError(err)); })
        .finally(function () { hoisting = false; });
    };

    new MutationObserver(function () {
      clearTimeout(pending);
      pending = setTimeout(sweep, 400);
    }).observe(document.getElementById('wcc-root'), {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['src', 'data-src'],
    });
  }

  /* ----------------------------------------------------------
     Save
     ---------------------------------------------------------- */
  var saveBtn = document.getElementById('wsSave');

  function buildPayload() {
    var payload = readTemplateMeta();

    // Only an overwrite carries a version; a create has nothing to conflict with.
    if (CFG.saveMethod === 'PUT') payload.version = CFG.version;

    try {
      payload.snapshot = window.__wccCap ? window.__wccCap() : null;
    } catch (e) {
      console.error('snapshot capture failed:', e);
      payload.snapshot = null;
    }

    return payload;
  }

  function describeError(err) {
    if (err && err.errors) {
      return Object.keys(err.errors).map(function (k) { return err.errors[k][0]; }).join(' ');
    }

    return (err && err.message) || 'Could not save. Check your connection and try again.';
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      var original = saveBtn.textContent;
      saveBtn.textContent = '💾 Saving…';

      hoistImages()
        .then(function () {
          // Capture only after the DOM holds URLs, never data URIs.
          return fetch(CFG.saveUrl, {
            method: CFG.saveMethod,
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': (window.COSTFLOW && window.COSTFLOW.csrf) || '',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(buildPayload())
          });
        })
        .then(function (res) {
          /* A stale save. Someone else wrote this record since we opened it,
             so refuse rather than silently overwrite their work. */
          if (res.status === 409) {
            return res.json().then(function (body) {
              conflictDialog(body.message);
              throw new Error('__handled__');
            });
          }

          return res.json().then(function (body) {
            if (!res.ok) throw body;
            return body;
          });
        })
        .then(function (body) {
          toast(body.message);
          CFG.version = body.record.version;

          /* A brand-new record now has an id — retarget future saves at it
             so a second click updates rather than creating a duplicate. */
          if (CFG.saveMethod === 'POST') {
            CFG.recordId = body.record.id;
            CFG.saveUrl = body.record.update_url;
            CFG.saveMethod = 'PUT';
            original = '💾 Update record';

            var name = document.getElementById('wsName');
            if (name) name.textContent = body.record.quo_no + ' — ' + body.record.client;

            localStorage.setItem('costflow_wcc_owner', String(body.record.id));
            window.history.replaceState({}, '', body.record.open_url);
          }

          // Persist the shrunken, URL-bearing snapshot into the local draft too.
          if (window.rs) window.rs();
        })
        .catch(function (err) {
          if (err && err.message === '__handled__') return;
          toast('⚠ ' + describeError(err));
        })
        .finally(function () {
          saveBtn.disabled = false;
          saveBtn.textContent = original;
        });
    });
  }

  /**
   * A losing write. Offer the only two honest choices: throw away this tab's
   * edits and reload, or keep them on screen and reconcile by hand.
   */
  function conflictDialog(message) {
    if (window.cfConfirm) {
      window.cfConfirm(
        'This record changed while you had it open',
        (message || 'Someone else saved it.') + ' Reload to see their version? Your unsaved changes in this tab will be lost.',
        function () {
          localStorage.removeItem('costflow_wcc_state_v7');
          window.location.reload();
        }
      );
      return;
    }

    toast('⚠ ' + message);
  }

  /* ----------------------------------------------------------
     Zoom (30–200%, persisted).
     transform:scale, never css zoom — zoom corrupts the template's pixel
     measurements (column gridding, drag-resize, sticky headers).
     ---------------------------------------------------------- */
  function getZoom() {
    var z = parseInt(localStorage.getItem(ZOOM_KEY), 10);
    return isFinite(z) ? Math.min(200, Math.max(30, z)) : 100;
  }

  function applyZoom(z) {
    z = Math.min(200, Math.max(30, Math.round(z / 5) * 5));
    localStorage.setItem(ZOOM_KEY, z);

    var f = z / 100;
    window.__wccZoom = f;

    $$('.wcc-doc').forEach(function (doc) {
      if (f === 1) {
        doc.style.transform = doc.style.transformOrigin = doc.style.width = '';
      } else if (f > 1) {
        doc.style.transformOrigin = '0 0';
        doc.style.transform = 'scale(' + f + ')';
        doc.style.width = (100 / f) + '%';
      } else {
        doc.style.transformOrigin = '50% 0';
        doc.style.transform = 'scale(' + f + ')';
        doc.style.width = '';
      }
    });

    var label = $('#wsZoomLbl');
    if (label) label.textContent = z + '%';

    try { if (window.growAll) window.growAll(); } catch (e) {}
  }

  function gridifySafe() {
    var z = getZoom();
    $$('.wcc-doc').forEach(function (d) { d.style.transform = d.style.width = ''; });
    try { if (window.__gridifyVisible) window.__gridifyVisible(); } catch (e) {}
    applyZoom(z);
  }

  function activeScroll() {
    var found = null;
    $$('.wcc-pane.on .wcc-scroll').forEach(function (el) { if (el.offsetParent !== null) found = el; });
    return found || $('.wcc-scroll');
  }

  function fitToScreen() {
    var scroll = activeScroll();
    if (!scroll) return;

    var doc = scroll.querySelector('.wcc-doc');
    if (!doc) return;

    var prevTransform = doc.style.transform;
    var prevWidth = doc.style.width;
    doc.style.transform = doc.style.width = '';

    var width = Math.max(doc.scrollWidth, doc.offsetWidth, 1);
    var z = ((scroll.clientWidth - 28) / width) * 100;

    doc.style.transform = prevTransform;
    doc.style.width = prevWidth;

    applyZoom(z);
    toast('Fitted to screen width (' + Math.min(200, Math.max(30, Math.round(z / 5) * 5)) + '%)');
  }

  function a4Size() { applyZoom(100); toast('A4 document size (100%)'); }

  $('#wsZoomIn').onclick = function () { applyZoom(getZoom() + 10); };
  $('#wsZoomOut').onclick = function () { applyZoom(getZoom() - 10); };
  $('#wsZoomLbl').onclick = a4Size;
  $('#fabFit').onclick = fitToScreen;
  $('#fabA4').onclick = a4Size;

  document.addEventListener('wheel', function (e) {
    if (!e.ctrlKey) return;
    if (!(e.target.closest && e.target.closest('.wcc-scroll'))) return;
    e.preventDefault();
    applyZoom(getZoom() + (e.deltaY < 0 ? 10 : -10));
  }, { passive: false });

  applyZoom(getZoom());

  /* ---------------------------------------------------------- Scroll pad */
  function nudge(dx, dy) {
    var scroll = activeScroll();
    if (scroll) scroll.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
  }

  $('#wsL').onclick = function () { nudge(-260, 0); };
  $('#wsR').onclick = function () { nudge(260, 0); };
  $('#wsU').onclick = function () { nudge(0, -240); };
  $('#wsD').onclick = function () { nudge(0, 240); };

  /* ------------------------------------------- Minimize / full screen */
  function refreshTpl() {
    setTimeout(function () {
      try { gridifySafe(); if (window.growAll) window.growAll(); } catch (e) {}
    }, 60);
  }

  var preZoom = null;

  function setMaxUI(on) {
    document.body.classList.toggle('cf-wccmax', on);
    $('#wsMax').textContent = on ? '🗗 Exit full screen' : '⛶ Maximize';

    var fab = $('#fabMax');
    if (fab) {
      fab.textContent = on ? '🗗' : '⛶';
      fab.title = on ? 'Exit full screen (Esc)' : 'Maximize / minimize — you can also double-click the tab bar';
    }

    if (on) {
      preZoom = getZoom();
      applyZoom(100);
    } else if (preZoom !== null) {
      applyZoom(preZoom);
      preZoom = null;
    }

    refreshTpl();
  }

  $('#wsMin').onclick = function () {
    var minimized = $('.cf-wccwrap').classList.toggle('min');
    this.textContent = minimized ? '🗖 Show template' : '🗕 Minimize';
    if (!minimized) refreshTpl();
  };

  $('#wsMax').onclick = function () {
    var goingFull = !document.body.classList.contains('cf-wccmax');

    if (goingFull) {
      var wrap = $('.cf-wccwrap');
      if (wrap.classList.contains('min')) {
        wrap.classList.remove('min');
        $('#wsMin').textContent = '🗕 Minimize';
      }
      setMaxUI(true);

      var section = document.querySelector('.cf-content');
      if (section && section.requestFullscreen) section.requestFullscreen().catch(function () {});
    } else {
      if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
      setMaxUI(false);
    }
  };

  $('#fabMax').onclick = function () { $('#wsMax').click(); };

  document.addEventListener('fullscreenchange', function () {
    if (!document.fullscreenElement && document.body.classList.contains('cf-wccmax')) setMaxUI(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('cf-wccmax') && !document.fullscreenElement) {
      setMaxUI(false);
    }
  });

  document.addEventListener('dblclick', function (e) {
    var bar = e.target.closest && e.target.closest('.wcc-tabbar');
    if (!bar) return;
    if (e.target.closest('button,select,input,textarea,a')) return;
    $('#wsMax').click();
  });

  /* Repaint once the layout has settled — sticky headers need real widths. */
  setTimeout(gridifySafe, 60);
})();
