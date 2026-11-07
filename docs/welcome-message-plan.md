# 📋 ApexSpend — Welcome Message for New Users

**Goal:** Every new user gets a welcome message — a **welcome email sent immediately at signup** plus a **one-time welcome card on the dashboard**.

**Status:** Plan only — approved by user, not yet implemented.

---

## 🔍 What the project already has

| Area | Finding |
|---|---|
| Signup | Handled in `auth.php` — creates the user, then redirects to login. **The user is NOT logged in yet at that point.** |
| Email system | Working. `includes/mailer.php` + `includes/email_templates.php` with 10 templates (transactions, budgets, family, password, profile). |
| ⚠️ Key catch | Every existing send function (`apex_mail_job()`) requires `$_SESSION['user']` — a logged-in user. At signup nobody is logged in, so the welcome email **cannot** use `defer_notification_email()` as-is. One small new send path is needed. |
| Dashboard | Has a time-based greeting ("Good morning, *Karan*."), but nothing special for a brand-new user. |
| Auto-migration pattern | `includes/email_prefs.php` does `SELECT` → fails → `ALTER TABLE` on first run. Reused for the new column (no manual phpMyAdmin step). |

---

## Part A — Welcome Email (sent once, at signup)

### Step 1 — `includes/email_templates.php`

Add a new template function `email_welcome(array $data)` at the end of the file (under the *Account* section):

- **Subject:** `👋 Welcome to ApexSpend, {first name}`
- **Body** built with the existing `email_wrap()` dark layout:
  - Heading: `👋 Welcome to ApexSpend, {name}`
  - Line: *"Your account is ready. Here's how to get the most out of it in the next 5 minutes."*
  - 3 numbered quick-start steps (styled like the existing cards):
    1. 💸 **Add your first entry** — record an expense or income
    2. 📊 **Set a budget** — monthly limits with 75 / 90 / 100% alerts
    3. 👨‍👩‍👧 **Create a family space** — share with family using an invite code
- Escape every value with `email_esc()` (existing pattern).
- Return `['subject' => ..., 'body' => ...]` like every other template.

### Step 2 — `includes/mailer.php`

Three small changes:

1. In `apex_build_email()`, add the case:
   ```php
   case 'welcome':
       return email_welcome($data);
   ```
2. Extract the queue / flush / shutdown logic currently inside `defer_notification_email()` into a shared private helper:
   ```php
   function apex_queue_job(array $job): void
   ```
   Behavior stays identical (flush response → `session_write_close()` → `register_shutdown_function()` delivers mail).
3. Add a new public function that takes the recipient **explicitly** (no session needed — this is the whole point):
   ```php
   function queue_welcome_email(string $name, string $email): void
   ```
   - Returns instantly if `!apex_mail_configured()` (same guard as everything else)
   - Builds the job manually: `['email' => $email, 'name' => $name, 'user_id' => 0, 'type' => 'welcome', 'data' => ['name' => $name]]`
   - **Skips** the `email_notifications` preference check — new users default to ON, and a welcome email is transactional
   - Calls `apex_queue_job()` → response flushes, email sends in shutdown

Refactor note: `defer_notification_email()` keeps its exact public behavior, it just calls `apex_queue_job($job)` internally now.

### Step 3 — `auth.php` (signup block only)

- Add at the top:
  ```php
  require_once 'includes/mailer.php';
  ```
- Change the end of the signup block (line ~64) to:
  ```php
  $_SESSION['flash_success'] = 'Account created. Sign in to continue.';
  header('Location: login.php?tab=login');
  queue_welcome_email($name, $email);   // flushes the redirect, email goes out after
  exit;
  ```
- This single call covers **both** signup paths — DB mode and the session-store fallback — because it sits after the shared success lines.
- While touching the INSERT, set `welcome_seen = 0` explicitly for the new row (arms Part B).

**Safety:** if SMTP is off or fails → function returns instantly, signup still works, error goes to `error_log` as usual. Same safety as every other email in the app.

---

## Part B — In-App Welcome Card (shows once on the dashboard)

### Step 4 — Database column

- Add `welcome_seen TINYINT(1) NOT NULL DEFAULT 0` to the `users` table.
- **Auto-migration**, same pattern as `apex_email_pref_ready()` in `includes/email_prefs.php` — first `SELECT welcome_seen` fails → `ALTER TABLE` runs → no phpMyAdmin step needed.
- Update `database.sql` so fresh installs include it.
- On the first-run check, mark **all existing users as seen** (`UPDATE users SET welcome_seen = 1` when the column was just added) → only brand-new signups ever see the card.

### Step 5 — `includes/welcome.php` (new file, ~40 lines)

Lightweight helpers, **no PHPMailer dependency** (mirrors `email_prefs.php`):

```php
function apex_welcome_ready(PDO $pdo): bool        // auto-ALTER check (static cache per request)
function apex_needs_welcome(PDO $pdo, int $userId): bool
function apex_mark_welcome_seen(PDO $pdo, int $userId): bool
```

### Step 6 — `dashboard.php`

- After the user/goals fetch (~line 115):
  ```php
  $showWelcome = $pdo ? apex_needs_welcome($pdo, (int) $user['id']) : false;
  ```
  (requires `require_once __DIR__ . '/includes/welcome.php';` at the top)
- Insert the welcome card **between the masthead and the KPI row**:
  - Kicker styled like the existing `§ 01` labels → `§ 00 · New here`
  - Heading: **Welcome to ApexSpend, {first name}.**
  - Sub: *"Three things to set up before your first full month of tracking."*
  - 3 action cells with buttons:
    - `Add your first entry` → `openModal()` (already exists on this page)
    - `Set a budget` → `budgets.php`
    - `Create a family space` → `family.php`
  - A **"Got it" dismiss button** → POST to the new endpoint
  - Use existing design tokens (`--c-border`, amber `#f59e0b`, card `#1a1a1a`) and existing `.ghost` / `.add` button styles so it looks native.

### Step 7 — `api_dismiss_welcome.php` (new file)

Same skeleton as `api_toggle_email.php`:

- session check → `csrf_validate()` → `apex_mark_welcome_seen($pdo, $userId)` → `echo json_encode(['success' => true])`
- Dashboard JS: on "Got it" click, POST it, then fade the card out and `showToast('Welcome aboard!', 'success')`.

---

## 📁 Files touched

| File | Type |
|---|---|
| `includes/email_templates.php` | modify — add `email_welcome()` |
| `includes/mailer.php` | modify — add `welcome` case, `apex_queue_job()` extract, `queue_welcome_email()` |
| `auth.php` | modify — require mailer, call `queue_welcome_email()`, insert sets `welcome_seen = 0` |
| `includes/welcome.php` | **new** — welcome helpers |
| `api_dismiss_welcome.php` | **new** — dismiss endpoint |
| `dashboard.php` | modify — welcome card + dismiss JS |
| `database.sql` | modify — add `welcome_seen` column |

---

## ✅ Test checklist

1. Sign up with your Gmail → redirect is **instant**, welcome email arrives within seconds
2. Log in → welcome card visible on the dashboard
3. Click "Got it" → card disappears, success toast shows
4. Log out / log in again → card does **not** come back
5. Existing user (e.g. `demo@apexspend.com`) → no card at all
6. Turn SMTP off / remove credentials → signup still works, no delay, no error shown to the user
7. Both signup paths tested — DB mode and session-store fallback (DB down)

---

## ⏱️ Estimated effort

| Step | Time |
|---|---|
| Step 1 — Welcome email template | ~10 min |
| Step 2 — Mailer send path | ~10 min |
| Step 3 — Wire into signup | ~5 min |
| Step 4–5 — Column + helpers | ~15 min |
| Step 6 — Dashboard card UI | ~25 min |
| Step 7 — Dismiss endpoint + JS | ~10 min |
| Testing | ~15 min |
| **TOTAL** | **~1.5 hours** |
