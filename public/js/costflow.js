/* ============================================================
   COSTFLOW — application shell.

   Everything the server-rendered pages need on top of plain HTML:
   toasts for flashed messages, the theme switch, the clock, confirm /
   prompt dialogs for destructive form posts, and the idle watchdog.
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.COSTFLOW || {};

  var $ = function (sel) { return document.querySelector(sel); };

  /* ---------------------------------------------------------------- Toast */
  var toastTimer = null;

  function toast(message) {
    var el = $('#cfToast');
    if (!el || !message) return;

    el.textContent = message;
    el.classList.add('on');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('on'); }, 2600);
  }

  window.cfToast = toast;

  /* Surface whatever the server flashed on this request. */
  if (CFG.flash) toast(CFG.flash);
  else if (CFG.errors && CFG.errors.length) toast('⚠ ' + CFG.errors[0]);

  /* ---------------------------------------------------------------- Theme */
  var themeBtn = $('#cfTheme');

  function paintThemeButton() {
    if (themeBtn) {
      themeBtn.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
    }
  }

  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('costflow_theme', next);
      paintThemeButton();
    });
  }

  paintThemeButton();

  /* ---------------------------------------------------------------- Clock */
  var clockTime = $('#cfClockT');
  var clockDate = $('#cfClockD');

  function tickClock() {
    if (!clockTime) return;
    var now = new Date();
    clockTime.textContent = now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
    clockDate.textContent = now.toLocaleDateString('en-MY', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  }

  tickClock();
  setInterval(tickClock, 15000);

  /* ---------------------------------------------------------------- Menu */
  var burger = $('#cfBurger');
  if (burger) burger.addEventListener('click', function () { $('#cfSide').classList.toggle('open'); });

  /* ------------------------------------------------------------- Modals */
  var modal = $('#cfMo');
  var modalCard = $('#cfMoCard');

  function closeModal() { modal.classList.remove('on'); }

  if (modal) {
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  }

  function confirmDialog(title, body, onYes) {
    modalCard.innerHTML =
      '<h3></h3><div class="cf-note" style="font-size:12.5px;margin-bottom:16px"></div>' +
      '<div class="cf-act" style="justify-content:flex-end">' +
      '<button class="cf-b" data-mo="no">Cancel</button>' +
      '<button class="cf-b p" data-mo="yes">Confirm</button></div>';

    modalCard.querySelector('h3').textContent = title;
    modalCard.querySelector('.cf-note').textContent = body;
    modal.classList.add('on');

    modalCard.querySelector('[data-mo="no"]').onclick = closeModal;
    modalCard.querySelector('[data-mo="yes"]').onclick = function () { closeModal(); onYes(); };
  }

  /* The WCC workspace uses this to surface a save conflict. */
  window.cfConfirm = confirmDialog;

  function promptDialog(label, onOk) {
    modalCard.innerHTML =
      '<h3>COSTFLOW</h3><div class="cf-fld"><label></label>' +
      '<input class="cf-in" data-mo="input" type="text"></div>' +
      '<div class="cf-act" style="justify-content:flex-end">' +
      '<button class="cf-b" data-mo="no">Cancel</button>' +
      '<button class="cf-b p" data-mo="yes">OK</button></div>';

    modalCard.querySelector('label').textContent = label;
    modal.classList.add('on');

    var input = modalCard.querySelector('[data-mo="input"]');
    input.focus();

    modalCard.querySelector('[data-mo="no"]').onclick = closeModal;
    modalCard.querySelector('[data-mo="yes"]').onclick = function () {
      var value = input.value;
      closeModal();
      onOk(value);
    };
  }

  /*
   * Destructive or note-taking form posts are gated behind a dialog.
   *
   *   data-confirm      on a <form>   — "are you sure?" before submitting
   *   data-prompt       on a <button> — ask for text first
   *   data-prompt-field on a <button> — which input to write the answer into
   *                                     (defaults to a hidden "note" field)
   */
  document.addEventListener(
    'click',
    function (e) {
      var button = e.target.closest('button[type="submit"]');
      if (!button) return;

      var form = button.form;
      if (!form) return;

      var prompt = button.dataset.prompt;
      var confirmText = form.dataset.confirm;

      if (!prompt && !confirmText) return;
      if (form.dataset.cfGo === '1') return; // already cleared, let it through

      e.preventDefault();
      e.stopPropagation();

      var submit = function () {
        form.dataset.cfGo = '1';
        form.submit();
      };

      if (prompt) {
        promptDialog(prompt, function (value) {
          if (value === null) return;

          var name = button.dataset.promptField || 'note';
          var field = form.querySelector('[name="' + name + '"]');

          if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            form.appendChild(field);
          }

          field.value = value;
          submit();
        });
        return;
      }

      confirmDialog('Are you sure?', confirmText, submit);
    },
    true
  );

  /* ------------------------------------------------------- Idle watchdog
     The server enforces the real timeout (EnforceIdleTimeout middleware).
     This only saves the user from discovering it on their next click. */
  if (CFG.idleMinutes && CFG.logoutUrl) {
    var lastActivity = Date.now();
    var limitMs = CFG.idleMinutes * 60 * 1000;

    ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'].forEach(function (evt) {
      document.addEventListener(evt, function () { lastActivity = Date.now(); }, { passive: true });
    });

    setInterval(function () {
      if (Date.now() - lastActivity < limitMs) return;

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = CFG.logoutUrl + '?reason=idle';

      var token = document.createElement('input');
      token.type = 'hidden';
      token.name = '_token';
      token.value = CFG.csrf;

      form.appendChild(token);
      document.body.appendChild(form);
      form.submit();
    }, 30000);
  }
})();
