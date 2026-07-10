/* COSTFLOW — sign-in screens. Show/hide password + live strength meter. */
(function () {
  'use strict';

  /* Show / hide password (delegated, covers every .cf-pwrap on the page). */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cf-eyebtn');
    if (!btn) return;

    var input = btn.parentElement.querySelector('input');
    if (!input) return;

    var reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    btn.textContent = reveal ? '🙈' : '👁';
    btn.title = reveal ? 'Hide password' : 'Show password';
    input.focus();
  });

  /* Live password requirements. Mirrors the server rule:
     min 8, upper, lower, number, symbol. */
  var pw = document.getElementById('rgPass');
  var req = document.getElementById('pwReq');

  if (pw && req) {
    var checks = {
      len: function (p) { return p.length >= 8; },
      up: function (p) { return /[A-Z]/.test(p); },
      lo: function (p) { return /[a-z]/.test(p); },
      num: function (p) { return /[0-9]/.test(p); },
      sym: function (p) { return /[^A-Za-z0-9]/.test(p); }
    };

    pw.addEventListener('input', function () {
      var value = this.value;
      req.querySelectorAll('span[data-req]').forEach(function (span) {
        var ok = checks[span.dataset.req](value);
        span.classList.toggle('ok', ok);
        span.textContent = (ok ? '✓ ' : '✕ ') + span.textContent.slice(2);
      });
    });
  }

  /* Spinner on submit, so a slow Brevo call never looks like a dead button. */
  document.addEventListener('submit', function (e) {
    var btn = e.target.querySelector('button.cf-btn[type="submit"]');
    if (btn) {
      btn.classList.add('busy');
      btn.disabled = true;
    }
  });
})();
