# 📋 ApexSpend — User-Defined Custom Categories

**Goal:** Let every user create, edit, recolor, and delete **their own categories** beyond the 7 built-in ones — backed by a MySQL table that becomes the single source of truth, so categories are no longer hardcoded across 6 files.

**Status:** Plan only — approved by user, not yet implemented.

---

## 🔍 What the project already has

| Area | Finding |
|---|---|
| Categories today | **Hardcoded in 6 places** with no single source of truth. `transactions.php` has a `$standardCategories` array; `dashboard.php` hardcodes 4 filter chips + 6 `<option>`s; `budgets.php` ships a `categories` map in its JSON payload; `app.js` uses a fixed 6-key object for the doughnut. |
| Icon lookup | `categoryIcon()` (JS) is **copy-pasted 7×** — `app.js:144`, `budgets.js:35`, `analytics.js:35`. `category_icon()` (PHP) **4×** — `dashboard.php:136`, `transactions.php:29`, `analytics.php:266`, `family.php:14`. All 11 maps are identical. |
| Schema | `transactions.category` is `VARCHAR(100) NOT NULL` — **no FK, no lookup table**. `budgets.category` is likewise a plain `VARCHAR(100)`. |
| Migration convention | Standalone re-runnable files: `migration_email.sql`, `migration_family_space.sql`. Plus an in-code auto-migration pattern in `includes/welcome.php` (SELECT → catch → ALTER). |
| Backend pattern | `api_*.php` endpoints, each: `require_once 'config.php'` → session check → `csrf_validate()` → PDO → `json_encode` response. |
| Charts | Chart.js. The dashboard doughnut (`app.js:669`) and analytics doughnut (`analytics.js:323`) both use **fixed label arrays + fixed color palettes** — custom categories would be invisible without rework. |
| Storage fallback | When MySQL is down, the app falls back to `$_SESSION['users_db']` (see `config.php:84`). New features must degrade gracefully, not fatal. |
| Styling | `style.css` — single design-system file. Editorial dark/light theme with OKLCH tokens, `--tangerine` accent, hairline rules. |
| Theme engine | `theme.js` + `html[data-theme="light"]` — any new UI must work in **both** themes. |

---

## 🎯 Design decisions (confirmed with user)

| Decision | Choice | Why |
|---|---|---|
| Scope | **Custom categories** (not a "customer" entity) | Fits the product — a personal expense tracker needs user-defined buckets, not CRM records. |
| Storage | **MySQL `categories` table** | Persists across devices, matches the DB-first design, needs a migration file. |
| Payload per category | **Name + icon + color** | Keeps ledger rows and charts visually distinct, matching the editorial look. |
| Charts | **Make them dynamic** | Custom categories must appear in the analytics/dashboard breakdown, not just dropdowns. |

---

## Part A — Schema & data layer

### Step 1 — `migration_custom_categories.sql` (new)

Mirrors the existing `migration_email.sql` / `migration_family_space.sql` convention — **safe to re-run**.

```sql
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(60) NOT NULL DEFAULT 'fa-tag',
    color VARCHAR(9) NOT NULL DEFAULT '#f59e0b',
    is_builtin TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_category (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Seed the 7 built-ins for `user_id = 1` so the demo account looks identical to today:

| Category | Icon | Color |
|---|---|---|
| Food & Dining | `fa-utensils` | `#f59e0b` |
| Housing & Utilities | `fa-bolt` | `#3b82f6` |
| Shopping & Retail | `fa-bag-shopping` | `#a855f7` |
| Transportation | `fa-car` | `#4ade80` |
| Entertainment | `fa-film` | `#ec4899` |
| Health & Medical | `fa-briefcase-medical` | `#22d3ee` |
| Income | `fa-money-bill-wave` | `#8a8a8a` |

Also append the `CREATE TABLE` + seed into **`database.sql`** so fresh installs get it too.

### Step 2 — `includes/categories.php` (new)

Mirrors `includes/welcome.php` (lightweight, no PHPMailer dependency):

- `apex_categories_ready(PDO $pdo): bool` — `SELECT` → catch → `CREATE TABLE IF NOT EXISTS` + seed built-ins. Auto-migrates, so no manual phpMyAdmin step.
- `apex_get_categories(PDO $pdo, int $userId): array` — returns rows ordered by `position, name`.
- `apex_builtin_categories(): array` — the canonical 7 (name → icon + color) in PHP.
- `apex_category_icon(string $name): string` — **single** PHP icon lookup, replacing the 4 duplicated `category_icon()` maps.

### Step 3 — `transactions.category` stays `VARCHAR(100)`

**No schema change, no backfill.** The seeded built-in names match exactly what's in the DB today, so every existing row keeps resolving correctly. This is what keeps the migration risk low.

---

## Part B — Backend endpoints

Three new files, each following the existing `api_*.php` shape exactly (`config.php` → session check → `csrf_validate()` → PDO → JSON):

| File | Method | Behavior |
|---|---|---|
| `api_get_categories.php` | GET | `{"success":true,"items":[{id,name,icon,color,is_builtin,position}]}` |
| `api_save_category.php` | POST | Create or edit. Accepts `name`, `icon`, `color`, optional `id`. |
| `api_delete_category.php` | POST | Delete. Refuses `is_builtin = 1`. |

**Cascade rules (the important part):**

- **Rename** → update the `categories` row **and** cascade the new name to `transactions.category` and `budgets.category` for that user, so history stays intact.
- **Delete** → **reassign** that category's transactions to `"Other"` and remove its `budgets` row. Never orphan a transaction.
- **Uniqueness** → `uq_user_category (user_id, name)`; the API catches the duplicate-key error and returns a friendly `"That category already exists."`
- **Built-ins** → not deletable, but **recolorable** (icon + color are presentation, so a user can restyle them).
- **Validation** → name 1–100 chars, icon whitelisted against the known Font Awesome set, color matched against `/^#[0-9a-fA-F]{6}$/`.

---

## Part C — Frontend centralization

### Step 4 — `category-helper.js` (new)

Loaded on every authenticated page (cache-busted with `?v=<?= time() ?>`), exposes `window.ApexCategories`:

- `get()` — fetch once, cache in memory
- `icon(name)` / `color(name)` — **replaces all 7 JS `categoryIcon()` maps**
- `populateSelect(el, opts)` — fills any `<select>` with the user's categories
- `applyToIcons(root)` — stamps the right icon on `.cat i` elements from `data-category`
- `CATEGORY_STYLE` — the canonical icon+color palette, read by both JS and PHP so seed data and UI can't drift

### Step 5 — Delete the 11 duplicated maps

| File | Remove |
|---|---|
| `app.js:144` | `categoryIcon()` |
| `budgets.js:35` | `categoryIcon()` |
| `analytics.js:35` | `categoryIcon()` |
| `dashboard.php:136` | `category_icon()` |
| `transactions.php:29` | `category_icon()` |
| `analytics.php:266` | `cat_icon()` |
| `family.php:14` | `category_icon()` |

Each is a mechanical delete-and-replace, but every page must be re-verified after.

---

## Part D — Make each page dynamic

| Page | Change |
|---|---|
| `transactions.php` | Drop `$standardCategories`. Read categories from the DB, then **merge with any category name found in existing transactions** so an old row never vanishes from the dropdown. Populate both `<select>`s + the filter chips server-side. |
| `dashboard.php` | Replace the 4 hardcoded filter chips and 6 hardcoded `<option>`s with the DB list. |
| `budgets.php` | `payload['categories']` becomes the DB list (name → icon + color). |
| `budgets.js` | `populateCategorySelect()` uses the live list. |
| `app.js` | `getCategoryData()` builds from the live category set instead of a fixed 6-key object. The doughnut's `labels` / `backgroundColor` / legend (`:679`, `:718`) all become dynamic. |
| `analytics.js` | Doughnut + legend colors come from each category's **stored color** (`:328` palette → per-category). |
| `analytics.php` | `cat_icon()` → shared helper. |
| `family.php` | `category_icon()` → shared helper. |

---

## Part E — The management UI

### Step 6 — `categories.php` + `categories.js` (new)

A dedicated page, added to the sidebar between **Budgets** and **Family** — first-class feature, own grid UI, matches the existing nav pattern.

- **Grid of category cards** — icon tile in the category's color, name, "Built-in" / "Custom" badge
- **Add** → modal with name + a Font Awesome **icon picker grid** + a **color swatch row** (tangerine-led palette, consistent with the app)
- **Edit** → same modal, pre-filled
- **Delete** → confirm dialog; built-ins show a **lock badge** and cannot be deleted
- Empty state when a user has no custom categories yet

### Step 7 — Inline "+ add category"

A small `+` next to the category dropdown in the **transaction modal** (`transactions.php`) and the **budget modal** (`budgets.php`) — so a user can create a category without abandoning the flow. Opens the same modal in "quick add" mode, then selects the new category.

---

## Part F — Safety & edge cases

- **No-DB fallback** → when MySQL is down, `apex_get_categories()` falls back to the 7 hardcoded built-ins so the app still runs (matches the `$_SESSION['users_db']` pattern in `config.php`).
- **CSRF** → all three new endpoints call `csrf_validate()`, consistent with every other write endpoint.
- **XSS** → all rendering uses `textContent` / `escapeHtml`, matching existing conventions. Category names are user input and get escaped everywhere.
- **Both themes** → new UI must work in `html[data-theme="light"]` and dark. Reuse existing tokens (`--paper-card`, `--hairline`, `--tangerine`) so it themes automatically.
- **Deleting a category that has budgets** → clean up the `budgets` row, or offer to keep it.
- **Cannot delete the last remaining category** → guard so the dropdowns can never end up empty.

---

## 📁 File manifest

**New (8)**

| File | Purpose |
|---|---|
| `migration_custom_categories.sql` | Schema + built-in seed |
| `includes/categories.php` | Shared helpers, auto-migration |
| `api_get_categories.php` | Fetch list |
| `api_save_category.php` | Create / edit |
| `api_delete_category.php` | Delete |
| `category-helper.js` | Frontend single source of truth |
| `categories.php` | Management page |
| `categories.js` | Management page logic |

**Modified (10)**

`database.sql`, `transactions.php`, `dashboard.php`, `budgets.php`, `budgets.js`, `analytics.php`, `analytics.js`, `app.js`, `family.php`, `style.css`

---

## 🗺️ Execution order

1. Schema + `includes/categories.php` + seed
2. Three API endpoints
3. `category-helper.js` → de-duplicate the 11 icon maps
4. Dynamic dropdowns + filter chips (transactions, dashboard, budgets)
5. Dynamic charts (app.js, analytics.js)
6. `categories.php` + `categories.js` management UI
7. Inline "+ add category" in the transaction/budget modals
8. Verify: seed data intact, rename cascade, delete reassign, no-DB fallback, light/dark themes

---

## ⚠️ Risk assessment

**Low.** `transactions.category` remains a plain `VARCHAR`, so **no existing row is touched or backfilled** — the 7 built-in names are seeded to match exactly what is in the DB today. The main risk is removing the 11 duplicated icon maps, which touches 6 files; each is mechanical, and every page gets re-verified before moving on.

**Verification checklist**
- [ ] Seed data renders identically to today (all 20 demo transactions resolve)
- [ ] Add a custom category → appears in transaction + budget dropdowns immediately
- [ ] Rename a category → old transactions follow the new name
- [ ] Delete a category → its transactions reassign to "Other", nothing orphans
- [ ] Built-ins cannot be deleted but can be recolored
- [ ] Custom category appears in the dashboard + analytics doughnut with its own color
- [ ] Works in both light and dark themes
- [ ] App still boots with MySQL down (falls back to built-ins)
- [ ] Duplicate category name returns a friendly error

---

## ❓ Open questions for the user

1. **Should the 7 built-ins be deletable?** Recommendation: **no** (locked, but recolorable) so the app never loses a sensible default. Say the word if you want them removable.
2. **Where should the management entry point live** — a dedicated sidebar page (`categories.php`), or inside `profile.php` Preferences? Recommendation: **dedicated page**, since it's a first-class feature with its own grid UI.
