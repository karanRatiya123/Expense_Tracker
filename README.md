# Expense Tracker

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)

A personal expense tracking dashboard built with PHP, Chart.js, and a GitHub-style contribution heatmap. Dark editorial aesthetic with Indian Rupee (₹) currency formatting.

**Owner & maintainer:** [@karanRatiya123](https://github.com/karanRatiya123)
**Co-developed with:** Claude (Anthropic) — AI pair-programmer

---

## Features

- **GitHub-style spending heatmap** — 53×7 cells covering 12 months, hover tooltips show daily outflow
- **KPI dashboard** — net balance, income, outflows, savings, with mini bar-chart sparklines
- **Editable financial goals** — monthly savings target and annual run-rate target, edited from the dashboard
- **Analytics** — monthly trend (line) and category breakdown (doughnut), colours follow your categories
- **Per-category budgets** — monthly limits with 75 / 90 / 100% threshold alerts
- **Custom categories** — per-user names, icons and colours; built-in categories seeded on first run
- **Editable ledger** — filterable transaction list with add/edit/delete and a quick-add modal
- **Family space** — create or join a shared ledger with an invite code, switch between personal and family views, and manage members
- **Fast entry** — searchable, keyboard-friendly category and payment-method dropdowns
- **Email notifications** — automatic Gmail receipts for transactions, budgets, family and account changes (per-user toggle)
- **New-user onboarding** — one-time welcome email at signup plus a dashboard card linking to the first three setup steps
- **Auth + profile** — login, signup, profile update, password change, session-based logout
- **Dark / light / system themes** — OKLCH tokens, hairline rules, no shadows

## Stack

- PHP (no framework, just session helpers in `config.php` and `api/auth.php`)
- Vanilla JavaScript (no build step)
- Chart.js for the line and doughnut charts
- Font Awesome 6 for icons
- Geist + Geist Mono + Instrument Serif for typography
- MySQL via XAMPP
- PHPMailer for Gmail SMTP notifications

## Setup

1. Drop the folder into `xampp/htdocs/`.
2. Start Apache + MySQL from the XAMPP control panel.
3. Import `database/database.sql` into MySQL. It creates the single `apexspend_db` database and all application tables.
   - There are no separate migration files to run. Missing optional columns (email notifications, welcome flag, custom categories) are added automatically on first use.
4. Install the mail dependency: `composer install` (creates `vendor/`).
5. Add Gmail SMTP credentials: copy your Gmail address + 16-character App Password into `mail_credentials.php` at the project root (git-ignored):
   ```php
   define('MAIL_USERNAME', 'you@gmail.com');
   define('MAIL_PASSWORD', 'abcdefghijklmnop');
   ```
   Emails are skipped until both are set. `APEX_MAIL_USER` / `APEX_MAIL_PASS` env vars also work.
6. Open `http://localhost/EXPENSE%20TRACKING%20WEBSITE/`.
   The root `index.php` sends signed-in users to `pages/dashboard.php` and everyone else to `pages/login.php`.
7. Sign up or log in — the dashboard renders once authenticated.

> Notification emails are sent *after* the HTTP response is flushed, so a slow
> SMTP round-trip (Gmail takes several seconds) never delays the UI.

**Requirements:** PHP 8.0+ with `pdo_mysql` and `openssl` enabled (both ship with XAMPP), plus Composer for step 4.

## Currency

All amounts are in **Indian Rupees (₹)** with lakh-grouping (e.g. `₹12,84,550.00`). The `formatINR()` helper in `assets/js/app.js` handles the conversion.

---

## Project structure

```
EXPENSE TRACKING WEBSITE/
├── index.php                    Entry point — redirects to pages/dashboard.php or pages/login.php
├── config.php                   DB credentials, session bootstrap, CSRF + API helpers
├── mail_credentials.php         Gmail address + App Password (git-ignored)
├── .htaccess                    Blocks direct HTTP access to internal folders/files
├── composer.json                PHPMailer requirement
├── composer.lock                Locked dependency versions
├── README.md
│
├── pages/                       User-facing views
│   ├── dashboard.php            Main dashboard: KPI tiles + heatmap + recent activity
│   ├── analytics.php            Monthly trend line chart + category doughnut
│   ├── budgets.php              Per-category budget bars and progress
│   ├── transactions.php         Filterable transaction ledger + add-transaction modal
│   ├── categories.php           Custom category manager (name, icon, colour)
│   ├── family.php               Family space: create, join, shared ledger, members
│   ├── login.php                Login + signup form (combined auth UI)
│   ├── profile.php              Account settings and profile editor
│   └── logout.php               Clears the PHP session
│
├── api/                         Form + AJAX endpoints
│   ├── auth.php                 Login / signup handler
│   ├── update_profile.php       Profile, password and goals update handler
│   ├── api_add_transaction.php  AJAX: add a transaction
│   ├── api_update_transaction.php AJAX: update a transaction
│   ├── api_delete_transaction.php AJAX: delete a transaction
│   ├── api_get_budgets.php      AJAX: fetch budgets
│   ├── api_save_budget.php      AJAX: save a budget
│   ├── api_delete_budget.php    AJAX: delete a budget
│   ├── api_update_goals.php     AJAX: save savings / run-rate targets
│   ├── api_get_categories.php   AJAX: list the user's categories
│   ├── api_save_category.php    AJAX: create or update a category
│   ├── api_delete_category.php  AJAX: delete a custom category
│   ├── api_create_family.php    AJAX: create a family space + invite code
│   ├── api_join_family.php      AJAX: join a family by invite code
│   ├── api_leave_family.php     AJAX: leave the current family
│   ├── api_get_member.php       AJAX: read a family member's profile
│   ├── api_remove_member.php    AJAX: remove a member (admin only)
│   ├── api_toggle_email.php     AJAX: email notification preference toggle
│   └── api_dismiss_welcome.php  AJAX: dismiss the one-time welcome card
│
├── assets/
│   ├── css/
│   │   └── style.css            Single design system: OKLCH tokens, layout, components
│   └── js/
│       ├── app.js               Dashboard: heatmap, KPI sparklines, shared helpers
│       ├── analytics.js         Analytics charts + palette sync with heatmap
│       ├── budgets.js           Budget bar rendering and progress calc
│       ├── transactions.js      Ledger filtering, search, modal wiring
│       ├── categories.js        Category manager interactions
│       ├── family.js            Family space create/join/member flows
│       ├── profile.js           Profile form submit + validation
│       ├── theme.js             Dark / light / system theme engine
│       ├── quickadd.js          Inline "new category" quick-add on the entry form
│       ├── apex-select.js       Searchable, keyboard-friendly select component
│       ├── category-helper.js   Shared category option rendering
│       └── script.js            Toast helper shared by every page
│
├── includes/                    Shared PHP libraries (never web-accessible)
│   ├── mailer.php               Sends notification emails (PHPMailer + guards)
│   ├── email_templates.php      HTML email templates for every event
│   ├── email_prefs.php          Per-user email preference helpers
│   ├── welcome.php              One-time welcome-card helpers
│   ├── login_guard.php          Login throttling against brute force
│   └── categories.php           Category seeding and read helpers
│
├── database/
│   └── database.sql             Complete MySQL schema and demo seed
│
├── docs/                        Architecture notes and feature plans
│   ├── APEXSPEND_MASTER_DOCUMENTATION.md
│   ├── email-notification-plan.md
│   ├── welcome-message-plan.md
│   └── custom-categories-plan.md
│
├── tests/                       CLI verification scripts
│   ├── test_env_check.php
│   └── run_phase5_execution.php
│
└── vendor/                      composer install output (git-ignored)
```

### URL layout

| Area | URL |
|---|---|
| Entry point | `/EXPENSE%20TRACKING%20WEBSITE/` |
| Pages | `/EXPENSE%20TRACKING%20WEBSITE/pages/dashboard.php` |
| AJAX / form endpoints | `/EXPENSE%20TRACKING%20WEBSITE/api/api_*.php` |
| CSS | `/EXPENSE%20TRACKING%20WEBSITE/assets/css/style.css` |
| JS | `/EXPENSE%20TRACKING%20WEBSITE/assets/js/*.js` |

Pages reference assets with `../assets/...`, call endpoints with `../api/...`, and include PHP libraries with `__DIR__ . '/../includes/...'`. The root `index.php` is the only file that must stay at the top level.

---

## File importance

Ranked by what actually breaks if the file is wrong or missing.

### Critical (breaks the app if removed)

| File | Why it matters |
|---|---|
| `config.php` | DB credentials, session bootstrap, CSRF and API helpers — every authenticated page depends on it. |
| `database/database.sql` | Source of truth for the MySQL schema. No DB = no data. |
| `api/auth.php` | Login / signup handler used by every session. |
| `api/api_add_transaction.php` | The only path for the add-transaction modal to persist data. |
| `api/api_update_transaction.php` | Endpoint for updating existing transactions. |
| `api/api_delete_transaction.php` | Endpoint for deleting transactions. |
| `api/api_get_budgets.php` | API endpoint for fetching budgets data. |
| `api/api_save_budget.php` | Endpoint for saving budget allocations. |
| `api/api_delete_budget.php` | Endpoint for deleting budget allocations. |
| `api/api_get_categories.php` | Feeds every category dropdown and chart. |
| `api/api_save_category.php` | Creates/updates categories used across the app. |
| `api/api_delete_category.php` | Removes custom categories safely. |
| `includes/categories.php` | Seeds built-in categories and normalises reads. |
| `api/update_profile.php` | Handles every profile-field, password and goals save. |
| `includes/mailer.php` | Single send path for all notification emails; fails safe when SMTP is unset. |
| `includes/email_templates.php` | All HTML email bodies — a broken template kills the notification, not the action. |
| `includes/email_prefs.php` | Adds/reads the `email_notifications` preference column. |
| `includes/welcome.php` | Adds/reads the one-time `welcome_seen` flag; marks existing accounts as seen. |
| `assets/css/style.css` | Single source of design tokens — removing it strips all theming. |

### High (core user experience)

| File | Why it matters |
|---|---|
| `pages/dashboard.php` | The default landing page after login. |
| `assets/js/app.js` | Heatmap + KPI sparklines — the signature visual. |
| `assets/js/analytics.js` | The line + doughnut charts on `pages/analytics.php`. |
| `assets/js/budgets.js` | Drives the per-category budget bars. |
| `assets/js/transactions.js` | Filter, search, and modal submission on the ledger page. |
| `assets/js/categories.js` | The category manager UI. |
| `assets/js/family.js` | Create/join/member flows on the family page. |
| `api/api_create_family.php` | Creates the shared space and invite code. |
| `api/api_join_family.php` | The only way into an existing family. |
| `api/api_leave_family.php` | Removes the member and their shared access. |
| `api/api_update_goals.php` | Persists dashboard goal edits. |
| `assets/js/theme.js` | Applies the stored dark/light/system preference before paint. |
| `assets/js/apex-select.js` | Powers every searchable dropdown in the app. |
| `assets/js/profile.js` | Wires the profile form to `api/update_profile.php`. |

### Medium (supporting)

| File | Why it matters |
|---|---|
| `index.php` | Redirect entry — replace with a landing page if you ever need one. |
| `pages/analytics.php` | Secondary view; the app still works without it. |
| `pages/budgets.php` | Secondary view; same. |
| `pages/transactions.php` | Secondary view; same. |
| `pages/categories.php` | Secondary view; same. |
| `pages/family.php` | Secondary view; same. |
| `pages/login.php` | Combined login/signup UI; the auth backend lives in `api/auth.php`. |
| `pages/profile.php` | Secondary view; same. |
| `pages/logout.php` | One-line session destroy. |
| `assets/js/quickadd.js` | Convenience shortcut inside the entry form. |
| `assets/js/category-helper.js` | Shared option markup for category chips/selects. |
| `assets/js/script.js` | Toast helper — used by every page. |
| `includes/login_guard.php` | Login throttle; fail-open if the attempts table is unavailable. |
| `docs/` | Architecture notes and feature plans — not needed at runtime. |
| `tests/` | CLI verification scripts — not needed at runtime. |
| `README.md` | Project documentation and setup guide. |
| `.gitignore` | Keeps credentials, dependencies and local-only material out of version control. |

---

## Architecture notes

- **No framework, no build step.** `pages/` owns the views, `assets/js/` owns the matching controllers, and `api/` owns every form/AJAX endpoint. This keeps the diff small and the deploy simple.
- **Shared helpers** live in `assets/js/app.js` (e.g. `formatINR()`); per-page scripts read from the same globals.
- **Auth is session-based.** `api/auth.php` verifies credentials and stores the signed-in user in `$_SESSION['user']`.
- **AJAX writes** go through small `api/api_*.php` endpoints — the modal in `pages/transactions.php` posts to `api/api_add_transaction.php` via the relative path `../api/api_add_transaction.php`.
- **Schema growth is additive.** Optional columns/tables are created by idempotent helpers (`SELECT` → `ALTER`/`CREATE`) or included in `database/database.sql`, so existing installs keep working without manual migrations.
- **Email never blocks the UI.** Notifications are queued, the HTTP response is flushed, then PHPMailer delivers over SMTP during shutdown. Failures are logged, never surfaced as user errors.

## Conventions

- All amounts stored as DECIMAL in MySQL, formatted to ₹ at the render layer only.
- Dates are ISO (`YYYY-MM-DD`) everywhere except the heatmap tooltip.
- Color tokens are OKLCH in `assets/css/style.css` — change one variable to retheme.
- JS is plain ES, no modules, no transpiler.
- Every state-changing request is POST + CSRF validated (`csrf_token()` / `csrf_validate()`).

## Repository hygiene

Never commit credentials or local-only work. `.gitignore` covers:

| Ignored | Why |
|---|---|
| `mail_credentials.php` | Gmail address + App Password |
| `vendor/` | Installed with `composer install` |
| `composer.phar` | Local Composer binary |
| `.sessions/` | PHP session files |
| `.mcp.json` | Local MCP client configuration |
| `.agentsroom/` | Local agent workspace and handoff files |
| `college/` | Project report, build scripts and images |
| `*.docx`, `generate_*_docx.py`, `__pycache__/`, `*.pyc` | Local documents and caches |
| `*.log`, `*.bak`, `*.tmp`, `.tmp_*` | Runtime artifacts |

`.htaccess` also blocks direct HTTP access to `includes/`, `database/`, `tests/`, `docs/`, `vendor/`, `.git/`, `.sessions/` and the sensitive root files.

---

## Contributors

- [@karanRatiya123](https://github.com/karanRatiya123) — design, product, backend, frontend
- **Claude (Anthropic)** — AI pair-programmer. Co-authored via `Co-Authored-By: Claude <noreply@anthropic.com>` on commits.

## License

Personal project. No license granted for reuse without permission from the maintainer.
