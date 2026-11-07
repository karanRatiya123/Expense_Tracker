# 📧 ApexSpend — Automatic Email Notification System

## Complete Implementation Plan (Step by Step)

> Every time a user does **anything** in ApexSpend — adds a transaction, edits a budget, joins a family — they get a **beautiful email** in their Gmail inbox with full details of what they did.

---

## 📋 Overview — What Emails Will Be Sent

| # | Action | Email Subject Example |
|---|---|---|
| 1 | Add Transaction | `💸 Expense Added: ₹11,800 on BigBasket` |
| 2 | Edit Transaction | `✏️ Transaction Updated: ₹11,800 on BigBasket` |
| 3 | Delete Transaction | `🗑️ Transaction Deleted: ₹11,800 on BigBasket` |
| 4 | Add/Edit Budget | `📊 Budget Set: ₹15,000 for Food & Dining` |
| 5 | Delete Budget | `📊 Budget Removed: Food & Dining` |
| 6 | Create Family | `👨‍👩‍👧 Family Created: Morgan Family` |
| 7 | Join Family | `👋 You Joined: Morgan Family` |
| 8 | Leave Family | `🚪 You Left: Morgan Family` |
| 9 | Password Changed | `🔒 Password Changed Successfully` |
| 10 | Profile Updated | `👤 Profile Updated Successfully` |

---

## 🗂️ Files You Will Create / Modify

### New Files (create these)
| File | Purpose |
|---|---|
| `composer.json` | Dependency manager config (for PHPMailer) |
| `includes/mailer.php` | Email helper — the ONE file that sends all emails |
| `includes/email_templates.php` | Beautiful HTML email templates |
| `migration_email.sql` | Database update to add email preferences |

### Files You Will Modify (small changes)
| File | What Changes |
|---|---|
| `config.php` | Add email settings (Gmail credentials) |
| `api_add_transaction.php` | Add 1 line to send email after success |
| `api_update_transaction.php` | Add 1 line to send email after success |
| `api_delete_transaction.php` | Add 1 line to send email after success |
| `api_save_budget.php` | Add 1 line to send email after success |
| `api_delete_budget.php` | Add 1 line to send email after success |
| `api_create_family.php` | Add 1 line to send email after success |
| `api_join_family.php` | Add 1 line to send email after success |
| `api_leave_family.php` | Add 1 line to send email after success |
| `update_profile.php` | Add 1 line to send email after success |
| `profile.php` | Add email notification ON/OFF toggle in settings |

---

## Step 1 — Install PHPMailer (Email Library)

PHPMailer is the **#1 PHP library** for sending emails. It works with Gmail.

### 1.1 — Install Composer (if you don't have it)

> [!NOTE]
> Composer is PHP's package manager — like npm for JavaScript. You only install it once.

1. Go to: https://getcomposer.org/download/
2. Download and run the Windows installer
3. It will auto-detect your XAMPP PHP at `C:\xampp\php\php.exe`
4. Click Next → Next → Install → Finish

### 1.2 — Install PHPMailer

Open **Command Prompt** or **Terminal** and run:

```bash
cd C:\xampp\htdocs\EXPENSE TRACKING WEBSITE
composer require phpmailer/phpmailer
```

This creates:
- `composer.json` — dependency file
- `composer.lock` — exact versions
- `vendor/` folder — PHPMailer library code

> [!IMPORTANT]
> After this step, you will have a `vendor/` folder. **Never delete it.**

---

## Step 2 — Create a Gmail App Password

> [!CAUTION]
> **Do NOT use your regular Gmail password.** Google blocks it. You need an **App Password** instead.

### 2.1 — Enable 2-Step Verification
1. Go to https://myaccount.google.com/security
2. Click **2-Step Verification** → Turn it ON (use your phone number)

### 2.2 — Generate App Password
1. Go to https://myaccount.google.com/apppasswords
2. In "App name" type: `ApexSpend`
3. Click **Create**
4. Google shows a **16-character password** like: `abcd efgh ijkl mnop`
5. **Copy it** (remove spaces) → `abcdefghijklmnop`
6. **Save this password** — you'll use it in Step 3

---

## Step 3 — Add Email Settings to config.php

Open [config.php](file:///C:/xampp/htdocs/EXPENSE%20TRACKING%20WEBSITE/config.php) and add these lines **after line 63** (after `ENABLE_RECAPS`):

```php
// ─── Email Notification Settings ─────────────────────────────────
define('MAIL_ENABLED',    true);                          // Set false to disable all emails
define('MAIL_HOST',       'smtp.gmail.com');               // Gmail SMTP server
define('MAIL_PORT',       587);                            // Gmail SMTP port (TLS)
define('MAIL_USERNAME',   'YOUR_EMAIL@gmail.com');         // ← Put YOUR Gmail here
define('MAIL_PASSWORD',   'abcdefghijklmnop');             // ← Put your App Password here
define('MAIL_FROM_NAME',  'ApexSpend');                    // Sender name in emails
```

> [!WARNING]
> Replace `YOUR_EMAIL@gmail.com` with your **real Gmail address** and `abcdefghijklmnop` with the **App Password** from Step 2.

---

## Step 4 — Run Database Migration

Create this file, then run it in **phpMyAdmin** (http://localhost/phpmyadmin):

### 4.1 — Create `migration_email.sql`

```sql
-- ============================================================
--  Email notification preferences — additive only
-- ============================================================
USE apexspend_db;

-- Add email notification toggle to users table
ALTER TABLE users 
ADD COLUMN email_notifications TINYINT(1) DEFAULT 1 
AFTER avatar_path;

-- 1 = emails ON (default), 0 = emails OFF
-- Users can toggle this from their Profile → Preferences
```

### How to run it:
1. Open http://localhost/phpmyadmin
2. Click on `apexspend_db` database
3. Click the **SQL** tab at the top
4. Paste the SQL code above
5. Click **Go**

---

## Step 5 — Create the Email Templates File

Create a new folder `includes/` and add this file:

### 5.1 — Create `includes/email_templates.php`

```php
<?php
/**
 * ApexSpend — HTML Email Templates
 * Beautiful, responsive email templates matching the editorial dark design.
 */

/**
 * Wrap any email body content in the base HTML layout.
 * Returns a complete HTML email string.
 */
function email_wrap($title, $bodyContent) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#0a0a0a; font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a; padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#141414; border-radius:12px; overflow:hidden; border:1px solid #222;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding:30px 40px 20px; border-bottom:1px solid #222;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <span style="font-size:22px; font-weight:700; color:#f5f5f5; letter-spacing:-0.5px;">Apex</span><span style="font-size:22px; font-weight:700; color:#f59e0b; letter-spacing:-0.5px;">Spend</span>
                                    </td>
                                    <td align="right">
                                        <span style="font-size:12px; color:#666;">' . date('d M Y, h:i A') . '</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:30px 40px;">
                            ' . $bodyContent . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 40px 30px; border-top:1px solid #222;">
                            <p style="margin:0; font-size:12px; color:#555; text-align:center;">
                                This is an automatic notification from ApexSpend.<br>
                                You can turn off email notifications in your 
                                <span style="color:#f59e0b;">Profile → Preferences</span>.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Format amount in Indian Rupee style (₹12,34,567.50)
 */
function email_format_inr($amount) {
    $amount = floatval($amount);
    $isNegative = $amount < 0;
    $amount = abs($amount);
    
    $parts = explode('.', number_format($amount, 2, '.', ''));
    $whole = $parts[0];
    $decimal = $parts[1];
    
    // Indian grouping: last 3 digits, then groups of 2
    if (strlen($whole) > 3) {
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $whole = $rest . ',' . $last3;
    }
    
    $formatted = '₹' . $whole . '.' . $decimal;
    return $isNegative ? '-' . $formatted : $formatted;
}

// ──────────────────────────────────────────────────────────────
//  TRANSACTION EMAILS
// ──────────────────────────────────────────────────────────────

function email_transaction_added($data) {
    $isExpense = ($data['type'] === 'expense');
    $color = $isExpense ? '#ef4444' : '#22c55e';
    $icon = $isExpense ? '💸' : '💰';
    $label = $isExpense ? 'Expense' : 'Income';
    
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">' . $icon . ' New ' . $label . ' Added</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">A new entry has been recorded in your ledger.</p>
        
        <!-- Amount Card -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; margin-bottom:25px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px;">Amount</p>
                    <p style="margin:0; font-size:36px; font-weight:700; color:' . $color . ';">' . email_format_inr($data['amount']) . '</p>
                </td>
            </tr>
        </table>

        <!-- Details Table -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;">
                    <span style="font-size:13px; color:#888;">Title</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;">
                    <span style="font-size:14px; color:#f5f5f5; font-weight:500;">' . htmlspecialchars($data['title']) . '</span>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;">
                    <span style="font-size:13px; color:#888;">Category</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;">
                    <span style="font-size:14px; color:#f59e0b; font-weight:500;">' . htmlspecialchars($data['category']) . '</span>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;">
                    <span style="font-size:13px; color:#888;">Payment Method</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;">
                    <span style="font-size:14px; color:#f5f5f5;">' . htmlspecialchars($data['method'] ?? 'Not specified') . '</span>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;">
                    <span style="font-size:13px; color:#888;">Type</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;">
                    <span style="display:inline-block; padding:3px 10px; border-radius:4px; font-size:12px; font-weight:600; background-color:' . ($isExpense ? '#3b1111' : '#0f2e1a') . '; color:' . $color . ';">' . strtoupper($label) . '</span>
                </td>
            </tr>' . (!empty($data['note']) ? '
            <tr>
                <td style="padding:12px 0;">
                    <span style="font-size:13px; color:#888;">Note</span>
                </td>
                <td style="padding:12px 0; text-align:right;">
                    <span style="font-size:14px; color:#aaa; font-style:italic;">' . htmlspecialchars($data['note']) . '</span>
                </td>
            </tr>' : '') . '
        </table>';

    $subject = $icon . ' ' . $label . ' Added: ' . email_format_inr($data['amount']) . ' on ' . $data['title'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

function email_transaction_updated($data) {
    $isExpense = ($data['type'] === 'expense');
    $color = $isExpense ? '#ef4444' : '#22c55e';
    
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">✏️ Transaction Updated</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">An entry in your ledger has been modified.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; margin-bottom:25px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px;">Updated Amount</p>
                    <p style="margin:0; font-size:36px; font-weight:700; color:' . $color . ';">' . email_format_inr($data['amount']) . '</p>
                </td>
            </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Title</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f5f5f5; font-weight:500;">' . htmlspecialchars($data['title']) . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Category</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f59e0b;">' . htmlspecialchars($data['category']) . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Payment Method</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f5f5f5;">' . htmlspecialchars($data['method'] ?? 'N/A') . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0;"><span style="font-size:13px; color:#888;">Type</span></td>
                <td style="padding:12px 0; text-align:right;"><span style="font-size:14px; color:' . $color . ';">' . strtoupper($data['type']) . '</span></td>
            </tr>
        </table>';

    $subject = '✏️ Transaction Updated: ' . email_format_inr($data['amount']) . ' on ' . $data['title'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

function email_transaction_deleted($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">🗑️ Transaction Deleted</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">An entry has been removed from your ledger.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; margin-bottom:20px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px;">Deleted Entry</p>
                    <p style="margin:0; font-size:20px; font-weight:600; color:#ef4444;">' . htmlspecialchars($data['title']) . '</p>
                    <p style="margin:8px 0 0; font-size:28px; font-weight:700; color:#888; text-decoration:line-through;">' . email_format_inr($data['amount']) . '</p>
                </td>
            </tr>
        </table>
        
        <p style="font-size:13px; color:#666; text-align:center;">Category: <span style="color:#f59e0b;">' . htmlspecialchars($data['category'] ?? 'N/A') . '</span></p>';

    $subject = '🗑️ Transaction Deleted: ' . email_format_inr($data['amount']) . ' — ' . $data['title'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

// ──────────────────────────────────────────────────────────────
//  BUDGET EMAILS
// ──────────────────────────────────────────────────────────────

function email_budget_saved($data) {
    $isNew = !empty($data['is_new']);
    $icon = $isNew ? '📊 New Budget Created' : '📊 Budget Updated';
    
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">' . $icon . '</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">Your spending limits have been ' . ($isNew ? 'set' : 'updated') . '.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; margin-bottom:25px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px;">Budget Limit</p>
                    <p style="margin:0; font-size:36px; font-weight:700; color:#f59e0b;">' . email_format_inr($data['amount']) . '</p>
                    <p style="margin:8px 0 0; font-size:14px; color:#888;">per ' . htmlspecialchars($data['period_type'] ?? 'month') . '</p>
                </td>
            </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Category</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f59e0b; font-weight:500;">' . htmlspecialchars($data['category']) . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Period</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f5f5f5;">' . ucfirst(htmlspecialchars($data['period_type'] ?? 'monthly')) . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0;"><span style="font-size:13px; color:#888;">Alert Thresholds</span></td>
                <td style="padding:12px 0; text-align:right;"><span style="font-size:14px; color:#f5f5f5;">' . htmlspecialchars($data['thresholds'] ?? '75%, 90%, 100%') . '</span></td>
            </tr>
        </table>';

    $subject = '📊 Budget ' . ($isNew ? 'Set' : 'Updated') . ': ' . email_format_inr($data['amount']) . ' for ' . $data['category'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

function email_budget_deleted($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">📊 Budget Removed</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">A spending limit has been removed from your account.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888;">Removed Budget</p>
                    <p style="margin:0; font-size:20px; font-weight:600; color:#ef4444;">' . htmlspecialchars($data['category'] ?? 'Unknown') . '</p>
                </td>
            </tr>
        </table>';

    $subject = '📊 Budget Removed: ' . ($data['category'] ?? 'Unknown');
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

// ──────────────────────────────────────────────────────────────
//  FAMILY EMAILS
// ──────────────────────────────────────────────────────────────

function email_family_created($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">👨‍👩‍👧 Family Space Created!</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">Your shared financial space is ready.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; margin-bottom:25px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888; text-transform:uppercase; letter-spacing:1px;">Family Name</p>
                    <p style="margin:0 0 15px; font-size:28px; font-weight:700; color:#f5f5f5;">' . htmlspecialchars($data['name']) . '</p>
                    <p style="margin:0 0 5px; font-size:12px; color:#888;">Invite Code</p>
                    <p style="margin:0; font-size:32px; font-weight:700; color:#f59e0b; letter-spacing:4px; font-family:monospace;">' . htmlspecialchars($data['invite_code']) . '</p>
                </td>
            </tr>
        </table>
        
        <p style="font-size:14px; color:#888; text-align:center;">Share this invite code with your family members so they can join your space.</p>';

    $subject = '👨‍👩‍👧 Family Created: ' . $data['name'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

function email_family_joined($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">👋 You Joined a Family!</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">You are now part of a shared financial space.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888;">Family</p>
                    <p style="margin:0; font-size:28px; font-weight:700; color:#22c55e;">' . htmlspecialchars($data['name']) . '</p>
                </td>
            </tr>
        </table>';

    $subject = '👋 You Joined: ' . $data['name'];
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

function email_family_left($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">🚪 You Left a Family</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">You have left the shared financial space.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0 0 5px; font-size:12px; color:#888;">Family</p>
                    <p style="margin:0; font-size:28px; font-weight:700; color:#ef4444;">' . htmlspecialchars($data['name'] ?? 'Your Family') . '</p>
                </td>
            </tr>
        </table>';

    $subject = '🚪 You Left: ' . ($data['name'] ?? 'Your Family');
    return ['subject' => $subject, 'body' => email_wrap($subject, $body)];
}

// ──────────────────────────────────────────────────────────────
//  ACCOUNT EMAILS
// ──────────────────────────────────────────────────────────────

function email_password_changed() {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">🔒 Password Changed</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">Your account password has been successfully updated.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a; border-radius:8px; border:1px solid #2a2a2a;">
            <tr>
                <td style="padding:25px; text-align:center;">
                    <p style="margin:0; font-size:14px; color:#22c55e;">✅ Password updated at ' . date('d M Y, h:i A') . '</p>
                </td>
            </tr>
        </table>
        
        <p style="margin:20px 0 0; font-size:13px; color:#ef4444; text-align:center;">If you did not make this change, please secure your account immediately.</p>';

    return ['subject' => '🔒 Password Changed Successfully', 'body' => email_wrap('Password Changed', $body)];
}

function email_profile_updated($data) {
    $body = '
        <h1 style="margin:0 0 5px; font-size:24px; color:#f5f5f5; font-weight:600;">👤 Profile Updated</h1>
        <p style="margin:0 0 25px; font-size:14px; color:#888;">Your profile information has been changed.</p>
        
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f;"><span style="font-size:13px; color:#888;">Name</span></td>
                <td style="padding:12px 0; border-bottom:1px solid #1f1f1f; text-align:right;"><span style="font-size:14px; color:#f5f5f5; font-weight:500;">' . htmlspecialchars($data['name']) . '</span></td>
            </tr>
            <tr>
                <td style="padding:12px 0;"><span style="font-size:13px; color:#888;">Email</span></td>
                <td style="padding:12px 0; text-align:right;"><span style="font-size:14px; color:#f5f5f5;">' . htmlspecialchars($data['email']) . '</span></td>
            </tr>
        </table>';

    return ['subject' => '👤 Profile Updated Successfully', 'body' => email_wrap('Profile Updated', $body)];
}
?>
```

---

## Step 6 — Create the Main Mailer Helper

This is the **core file** — it handles sending emails using PHPMailer.

### 6.1 — Create `includes/mailer.php`

```php
<?php
/**
 * ApexSpend — Mailer Helper
 * 
 * ONE simple function: send_notification_email()
 * Call it from any API file to send an email to the current user.
 * 
 * Usage:
 *   require_once 'includes/mailer.php';
 *   send_notification_email('transaction_added', $data);
 */

require_once __DIR__ . '/../vendor/autoload.php';  // PHPMailer
require_once __DIR__ . '/email_templates.php';       // HTML templates

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send a notification email to the currently logged-in user.
 *
 * @param string $type     — Email type (e.g., 'transaction_added', 'budget_saved')
 * @param array  $data     — Data to fill the template (amount, title, category, etc.)
 * @return bool            — true if email sent, false if skipped/failed
 *
 * Supported types:
 *   'transaction_added'    — New transaction created
 *   'transaction_updated'  — Transaction edited
 *   'transaction_deleted'  — Transaction deleted
 *   'budget_saved'         — Budget created or updated
 *   'budget_deleted'       — Budget deleted
 *   'family_created'       — Family space created
 *   'family_joined'        — User joined a family
 *   'family_left'          — User left a family
 *   'password_changed'     — Password was changed
 *   'profile_updated'      — Profile info was updated
 */
function send_notification_email($type, $data = []) {
    // ── Guard checks ─────────────────────────────────────────
    
    // 1. Is email feature enabled?
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        return false;
    }

    // 2. Is user logged in?
    if (!isset($_SESSION['user']['email'])) {
        return false;
    }

    // 3. Does user want email notifications?
    $userEmail = $_SESSION['user']['email'];
    $userId = $_SESSION['user']['id'];
    
    // Check user's email_notifications preference from database
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT email_notifications FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if ($row && (int)$row['email_notifications'] === 0) {
                return false;  // User turned off notifications
            }
        } catch (PDOException $e) {
            // Column might not exist yet — continue anyway
        }
    }

    // ── Build email from template ─────────────────────────────
    $email = null;
    
    switch ($type) {
        case 'transaction_added':
            $email = email_transaction_added($data);
            break;
        case 'transaction_updated':
            $email = email_transaction_updated($data);
            break;
        case 'transaction_deleted':
            $email = email_transaction_deleted($data);
            break;
        case 'budget_saved':
            $email = email_budget_saved($data);
            break;
        case 'budget_deleted':
            $email = email_budget_deleted($data);
            break;
        case 'family_created':
            $email = email_family_created($data);
            break;
        case 'family_joined':
            $email = email_family_joined($data);
            break;
        case 'family_left':
            $email = email_family_left($data);
            break;
        case 'password_changed':
            $email = email_password_changed();
            break;
        case 'profile_updated':
            $email = email_profile_updated($data);
            break;
        default:
            return false;
    }

    if (!$email) return false;

    // ── Send email via PHPMailer ──────────────────────────────
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;        // smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;     // your Gmail
        $mail->Password   = MAIL_PASSWORD;     // app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;         // 587

        // From / To
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $_SESSION['user']['name'] ?? '');

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $email['subject'];
        $mail->Body    = $email['body'];
        
        // Plain-text fallback (strip HTML tags)
        $mail->AltBody = strip_tags(
            str_replace(['<br>', '<br/>', '<br />'], "\n", $email['body'])
        );

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Email failed — log it but don't break the app
        error_log("ApexSpend Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
```

> [!IMPORTANT]
> This function **never breaks your app**. If the email fails (no internet, wrong password, etc.), it just logs the error and moves on. The user's action still completes normally.

---

## Step 7 — Wire Emails Into Your API Files

Now the easy part — add **just 1-2 lines** in each API file.

### 7.1 — `api_add_transaction.php`

Add these 2 lines. First, add the require at the top (after `require_once 'config.php';`):

```php
require_once 'includes/mailer.php';
```

Then, **inside the `if ($success)` block**, after the budget auto-creation logic (after line 48), add:

```php
        // Send email notification
        send_notification_email('transaction_added', [
            'title'    => $title,
            'amount'   => $amount,
            'category' => $category,
            'method'   => $method,
            'type'     => $type,
            'note'     => $note,
        ]);
```

### 7.2 — `api_update_transaction.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

After the `if ($success)` on line 59, add before the JSON response:
```php
        send_notification_email('transaction_updated', [
            'title'    => $title,
            'amount'   => $amount,
            'category' => $category,
            'method'   => $method,
            'type'     => $type,
            'note'     => $note,
        ]);
```

### 7.3 — `api_delete_transaction.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

**Before the DELETE query**, fetch the transaction details first (so we know what was deleted):
```php
// Fetch details before deleting (for email)
$detail = $pdo->prepare('SELECT title, amount, category, type FROM transactions WHERE id = ? AND user_id = ?');
$detail->execute([$id, $_SESSION['user']['id']]);
$txData = $detail->fetch();
```

Then in the `if ($success && $stmt->rowCount() > 0)` block, add:
```php
        if ($txData) {
            send_notification_email('transaction_deleted', $txData);
        }
```

### 7.4 — `api_save_budget.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

Before the final `echo json_encode(['success' => true]);` on line 48, add:
```php
send_notification_email('budget_saved', [
    'category'    => $category,
    'amount'      => $amount,
    'period_type' => $periodType,
    'thresholds'  => $thresholds,
    'is_new'      => !$exists,
]);
```

### 7.5 — `api_delete_budget.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

**Before the DELETE query**, fetch the category name:
```php
$info = $pdo->prepare('SELECT category FROM budgets WHERE id = ? AND user_id = ?');
$info->execute([$id, $userId]);
$budgetData = $info->fetch();
```

After the DELETE, add:
```php
if ($budgetData) {
    send_notification_email('budget_deleted', $budgetData);
}
```

### 7.6 — `api_create_family.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

After `$pdo->commit();` on line 57, add:
```php
        send_notification_email('family_created', [
            'name'        => $name,
            'invite_code' => $code,
        ]);
```

### 7.7 — `api_join_family.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

After the successful INSERT (line 49-50), add:
```php
    send_notification_email('family_joined', ['name' => $family['name']]);
```

### 7.8 — `api_leave_family.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

After successful leave, add:
```php
send_notification_email('family_left', ['name' => $familyName]);
```
(You'll need to fetch the family name before deleting — same pattern as delete transaction.)

### 7.9 — `update_profile.php`

Add require at the top:
```php
require_once 'includes/mailer.php';
```

In the `change_password` section (after line 115), add:
```php
    send_notification_email('password_changed');
```

In the `update_info` section (after line 87), add:
```php
    send_notification_email('profile_updated', ['name' => $name, 'email' => $email]);
```

---

## Step 8 — Add Email Toggle in Profile Page

Add a toggle switch in the **Preferences** section of [profile.php](file:///C:/xampp/htdocs/EXPENSE%20TRACKING%20WEBSITE/profile.php) so users can turn emails ON or OFF.

### 8.1 — Add this HTML in the Preferences section of `profile.php`

Find the preferences section and add this toggle:

```html
<!-- Email Notifications Toggle -->
<div class="pref-row" style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid var(--c-border);">
    <div>
        <p style="margin:0; font-size:14px; color:var(--c-text); font-weight:500;">📧 Email Notifications</p>
        <p style="margin:4px 0 0; font-size:12px; color:var(--c-text-muted);">Get emails when you add, edit, or delete transactions</p>
    </div>
    <label class="toggle-switch" style="position:relative; display:inline-block; width:48px; height:26px;">
        <input type="checkbox" id="emailToggle" <?= $emailNotif ? 'checked' : '' ?> 
               onchange="toggleEmailNotifications(this.checked)"
               style="opacity:0; width:0; height:0;">
        <span style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#333; border-radius:26px; transition:0.3s;">
        </span>
    </label>
</div>

<style>
    .toggle-switch input:checked + span { background-color: #f59e0b; }
    .toggle-switch span:before {
        content: "";
        position: absolute;
        height: 20px; width: 20px;
        left: 3px; bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }
    .toggle-switch input:checked + span:before { transform: translateX(22px); }
</style>

<script>
function toggleEmailNotifications(enabled) {
    const formData = new FormData();
    formData.append('enabled', enabled ? '1' : '0');
    formData.append('_token', '<?= csrf_token() ?>');
    
    fetch('api_toggle_email.php', { 
        method: 'POST', 
        body: formData,
        headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast(enabled ? 'Email notifications turned ON' : 'Email notifications turned OFF', 'success');
        }
    });
}
</script>
```

### 8.2 — Fetch the current setting at the top of `profile.php`

Add this **in the PHP section** at the top of profile.php (where user data is fetched):

```php
// Get email notification preference
$emailNotif = 1; // default ON
if ($pdo) {
    try {
        $stmtEmail = $pdo->prepare('SELECT email_notifications FROM users WHERE id = ?');
        $stmtEmail->execute([$_SESSION['user']['id']]);
        $emailRow = $stmtEmail->fetch();
        if ($emailRow) {
            $emailNotif = (int)$emailRow['email_notifications'];
        }
    } catch (PDOException $e) {
        $emailNotif = 1;
    }
}
```

### 8.3 — Create `api_toggle_email.php`

```php
<?php
/**
 * ApexSpend — Toggle email notifications ON/OFF
 */
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

csrf_validate();

$enabled = (int)($_POST['enabled'] ?? 1);
$userId = (int)$_SESSION['user']['id'];

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET email_notifications = ? WHERE id = ?');
    $stmt->execute([$enabled, $userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to update preference']);
}
?>
```

---

## Step 9 — Test Everything

### 9.1 — Quick Test Checklist

| # | Test | How to Check |
|---|---|---|
| 1 | ✅ PHPMailer installed | Check `vendor/phpmailer/` folder exists |
| 2 | ✅ Gmail App Password works | Add a test transaction → check your Gmail inbox |
| 3 | ✅ Add Transaction email | Add an expense → beautiful email arrives |
| 4 | ✅ Edit Transaction email | Edit a transaction → email with updated details |
| 5 | ✅ Delete Transaction email | Delete a transaction → email confirms deletion |
| 6 | ✅ Add Budget email | Create a budget → email shows limit and category |
| 7 | ✅ Delete Budget email | Delete a budget → confirmation email |
| 8 | ✅ Create Family email | Create family → email shows invite code |
| 9 | ✅ Join Family email | Join with code → welcome email |
| 10 | ✅ Change Password email | Change password → security alert email |
| 11 | ✅ Profile Update email | Update name → confirmation email |
| 12 | ✅ Toggle OFF works | Turn off in profile → no more emails |
| 13 | ✅ Toggle ON works | Turn back on → emails resume |

### 9.2 — Common Errors and Fixes

| Error | Cause | Fix |
|---|---|---|
| `SMTP connect() failed` | Wrong Gmail credentials | Double-check App Password, no spaces |
| `Authentication failed` | Using regular password | Must use App Password (Step 2) |
| `Could not connect to SMTP` | Port blocked | Try port `465` with `ENCRYPTION_SMTPS` |
| `Class PHPMailer not found` | Composer not run | Run `composer require phpmailer/phpmailer` |
| `File not found: vendor/autoload.php` | Missing vendor folder | Run `composer install` |

---

## 📁 Final File Structure

After implementing everything:

```
EXPENSE TRACKING WEBSITE/
├── includes/                      ← NEW folder
│   ├── mailer.php                 ← NEW — email sending helper
│   └── email_templates.php        ← NEW — HTML email templates
├── vendor/                        ← NEW — PHPMailer library (from Composer)
│   └── phpmailer/
├── composer.json                  ← NEW — Composer config
├── composer.lock                  ← NEW — lock file
├── api_toggle_email.php           ← NEW — toggle notifications
├── migration_email.sql            ← NEW — database migration
├── config.php                     ← MODIFIED — added email settings
├── api_add_transaction.php        ← MODIFIED — +2 lines
├── api_update_transaction.php     ← MODIFIED — +2 lines
├── api_delete_transaction.php     ← MODIFIED — +5 lines
├── api_save_budget.php            ← MODIFIED — +2 lines
├── api_delete_budget.php          ← MODIFIED — +4 lines
├── api_create_family.php          ← MODIFIED — +2 lines
├── api_join_family.php            ← MODIFIED — +2 lines
├── api_leave_family.php           ← MODIFIED — +2 lines
├── update_profile.php             ← MODIFIED — +2 lines
├── profile.php                    ← MODIFIED — added toggle switch
└── ... (everything else stays the same)
```

---

## ⏱️ Total Time Estimate

| Step | Time |
|---|---|
| Step 1 — Install PHPMailer | 5 minutes |
| Step 2 — Gmail App Password | 5 minutes |
| Step 3 — Config settings | 2 minutes |
| Step 4 — Database migration | 2 minutes |
| Step 5 — Email templates | Already written above, just copy |
| Step 6 — Mailer helper | Already written above, just copy |
| Step 7 — Wire into API files | 15 minutes (copy-paste 1-2 lines per file) |
| Step 8 — Profile toggle | 10 minutes |
| Step 9 — Testing | 10 minutes |
| **TOTAL** | **~50 minutes** |

> [!TIP]
> You can do Step 1-4 first, then Step 5-6, then Step 7 one file at a time. Test after each file so you can catch issues early.
