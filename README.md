# COSTFLOW

WCC cost-estimation and documentation platform for **BPE Energy Sdn. Bhd.**
A Laravel backend behind the approved COSTFLOW v2.1 design, converted to Blade.

Digitalizes the Excel-based WCC workflow: **WCC1** planned budget → **BPE Price**
customer quotation → **WCC2** actual cost capture — centralized, role-governed
and auditable.

---

## Requirements

- PHP 8.3+ (developed on 8.5)
- Composer 2
- SQLite (local) or MySQL 8 (production)

## Getting started

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open <http://localhost:8000>.

### Demo accounts

Password for all four: `Costflow@123`. You may type just the local part on the
sign-in form — `@bpe.com.my` is appended automatically.

| Sign in as | Role       | Can do                                                                |
|------------|------------|-----------------------------------------------------------------------|
| `admin`    | IT         | Administer users, unlock accounts, clear the audit log, delete records |
| `isnari`   | Management | Approve / return submitted WCCs                                       |
| `ira.lee`  | Management | Approve / return submitted WCCs                                       |
| `alfi`     | Engineer   | Cost in the workspace, save and submit WCCs                           |

---

## Email (Brevo)

All four email flows go through Brevo's transactional API:

1. **Email verification** on registration — the link also signs the user in
2. **Password reset**
3. **Passwordless sign-in** — a 6-digit code plus a one-click signed magic link
4. **Workflow notifications** — Management on submit; the owning engineer on approve / return

To send for real, put your key in `.env` and switch the mailer:

```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY=xkeysib-…
MAIL_FROM_ADDRESS="costflow@coursesme.com"
```

The sender is **not** `@bpe.com.my`. You can only send as a domain you can add
DKIM records to, and BPE's DNS is not ours. An unauthenticated sender does not
bounce — Brevo silently rewrites the From to `…@brevosend.com`, so the mail
arrives from a stranger and your domain builds no reputation. Recipients remain
`@bpe.com.my`; only the sender differs.

Out of the box `MAIL_MAILER=log`, so a fresh clone works with no key: every
email is written to `storage/logs/mail.log`, links included.

Workflow notifications are queued. In production, run a worker:

```bash
php artisan queue:work
```

---

## Production (MySQL)

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=costflow
DB_USERNAME=costflow
DB_PASSWORD=…
```

```bash
php artisan migrate --force
php artisan optimize          # config + route + view caches
```

Every query is written to run unchanged on both SQLite and MySQL — no
`DATE_FORMAT` / `strftime`, and `LIKE` escaping uses `!` rather than a
backslash, which the two engines treat differently inside string literals.

### `display_errors` must be off

`RejectOversizedRequest` turns a too-large body into a real `413`. It can only
do that when `display_errors` is **off**, which is the production default: with
it on, PHP echoes its "POST Content-Length exceeds the limit" warning during
request startup, before any application code runs, which commits a `200` status
line that nothing can take back.

Locally you will therefore see a `200` and a PHP warning instead of the `413`.
Run `php -d display_errors=0 …` to see the real behaviour.

---

## Domain model

| Table                  | Holds                                                                        |
|------------------------|------------------------------------------------------------------------------|
| `users`                | name, email, role, phone, verification, lockout counters                     |
| `wcc_records`          | header fields, planned/selling/actual money, status, and the sheet `snapshot`|
| `wcc_status_histories` | every status change, who made it, and any note                               |
| `wcc_attachments`      | signature and stamp images, content-addressed by SHA-256                     |
| `audit_logs`           | append-only activity trail; survives user deletion                           |
| `login_codes`          | hashed one-time sign-in codes                                                |

### The `snapshot` column

The spreadsheet's own state, exactly as its `cap()` function serializes it —
stored verbatim as JSON and **never parsed server-side**. The header fields and
grand totals are *also* extracted into real columns (`quo_no`, `client`,
`planned_cost`, `selling`, `actual`, …) so records stay queryable for the
records list and the analytics pages.

Snapshots stay small because images do **not** live in them (see below).

### Signatures and stamps

The engine writes uploaded and hand-drawn images into the DOM as base64 data
URIs, and `cap()` folds them into the snapshot. Two of them push a save past
PHP's `post_max_size`, where the body is discarded *during startup* — Laravel
never runs, the browser gets a 200, and the engineer believes their WCC saved.
It did not.

So `wcc-workspace.js` watches the sheet and, the instant a data URI appears,
downscales it, uploads it to `wcc_attachments`, and swaps a URL back into the
DOM. A 4.4 MB stamp becomes an 11 KB snapshot. Identical images share a
SHA-256 and are stored once, so re-saving never re-uploads.

Files live in `storage/app/private/wcc-attachments` — outside the webroot,
served only through an authenticated route with `nosniff` and a locked-down
`Content-Security-Policy`. SVG is rejected: it can carry script.

### Concurrency

Every record carries a `version`. A save sends the version it loaded, and the
server writes with `WHERE version = ?`, so two tabs racing on the same record
cannot both win. The loser gets a `409` and a dialog rather than silently
destroying a colleague's costing.

### When the money is booked

`approved_at` is stamped at the moment management approves, and analytics
buckets months by it. Bucketing by `updated_at` — as the prototype did — meant
that fixing a typo in a March job moved its selling value into July's revenue.

### Workflow

```
Draft ──▶ Costed ──▶ Submitted ──▶ Approved
                         │           (frozen)
                         └──▶ Returned ──▶ Submitted
```

- Engineers move their own records to **Costed** and **Submitted**
- Only Management may **Approve** or **Return**
- **Approved** records are read-only; **Submitted** ones are locked while under review
- Only IT may delete a record or clear the audit log

Transitions are enforced twice: `WccRecordPolicy` decides *who*, and
`WccRecord::TRANSITIONS` decides *which moves exist at all*.

### Sign-in security

Three wrong passwords pause the account for five minutes (configurable in
`config/costflow.php`). Sessions expire after 30 idle minutes, enforced by the
`EnforceIdleTimeout` middleware rather than trusted to the browser.

---

## Front-end layout

The original single-file prototype was split, not rewritten:

| File                                  | What it is                                                       |
|---------------------------------------|------------------------------------------------------------------|
| `public/js/wcc-engine.js`             | The WCC1 / BPE Price / WCC2 spreadsheet engine, lifted verbatim  |
| `public/js/wcc-workspace.js`          | The only bridge between that engine and the server               |
| `public/js/costflow.js`               | Shell: toasts, theme, clock, dialogs, idle watchdog              |
| `public/css/costflow.css`             | App shell styles                                                 |
| `public/css/wcc-template.css`         | Document styles for the three sheets                             |
| `resources/views/wcc/template.blade.php` | The sheet markup — **element ids are load-bearing**           |

Pages are server-rendered: each section of the old client-side router is now a
real route, controller and view. Charts are drawn from server data as SVG and
CSS rather than assembled in JavaScript.

> The engine drives the template by element id. Renaming an id in
> `template.blade.php` will silently break a calculation.

### One deviation from the original engine

`wcc-engine.js` is byte-for-byte the prototype's script except for a single
fix. Several WCC2 elements have ids beginning with a digit (`#2As`, `#2tot`,
`#2mam`). Those are legal HTML ids but **illegal CSS identifiers**, so
`querySelector('#2As')` throws a `SyntaxError`. `calc()` aborted at that line
on every run, which silently killed the WCC2 subtotals, the variance analysis,
the lump-sum view and the entire Manager tab. Lookups now go through a `byId()`
helper that applies `CSS.escape`.

### Company logo

Drop it at `public/img/bpe-logo.png` and it appears on all three documents.
Without it the letterhead renders as text only.

---

## Tests

```bash
php artisan test
```

42 feature tests cover the sign-in lockout, email verification, the OTP and
magic-link flows, every workflow transition, the role matrix, snapshot save and
validation, attachment upload and hardening, optimistic locking, the `413`
guard, and the `approved_at` bucketing.

Each protection has been mutation-checked: removing the version guard, the
policy, the `approved_at` bucketing or the size guard makes the corresponding
tests fail.
