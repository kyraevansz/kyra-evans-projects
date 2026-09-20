# The Yard Fund — Team Setup Guide

> Built by VSU students, for VSU students.  
> Plant a seed, grow a future.

---

## What's in this ZIP

```
yard_fund_demo/
├── All PHP files
├── PHPMailer/          ← email library, do not modify
├── uploads/            ← proof files and photos go here
├── style.css
├── migration.sql       ← run this once to build the database
├── fix_admin_password.php  ← run once then DELETE
└── README.md           ← you are here
```

---

## Step 1 — Install XAMPP

If you don't have XAMPP installed:

1. Download it from **https://www.apachefriends.org**
2. Install with default settings
3. Open the **XAMPP Control Panel**
4. Click **Start** next to **Apache**
5. Click **Start** next to **MySQL**

Both should show green. Keep XAMPP running whenever you work on the project.

---

## Step 2 — Add the project files

1. Extract the ZIP you received
2. Copy the entire `yard_fund_demo` folder into:
   ```
   C:\xampp\htdocs\
   ```
   So the path looks like:
   ```
   C:\xampp\htdocs\yard_fund_demo\
   ```
3. Make sure there is an `uploads` folder inside it. If not, create an empty folder called `uploads` there now.

---

## Step 3 — Create the database

1. Open your browser and go to:
   ```
   http://localhost/phpmyadmin
   ```
2. In the left sidebar, click **New**
3. Type `yard_fund_demo` as the database name
4. Set collation to `utf8mb4_general_ci`
5. Click **Create**

---

## Step 4 — Import the database

1. Click on `yard_fund_demo` in the left sidebar to select it
2. Click the **Import** tab at the top
3. Click **Choose File**
4. Select `migration.sql` from inside your `yard_fund_demo` folder
5. Scroll down and click **Go**

You should see a green success message. All tables will appear in the left sidebar.

---

## Step 5 — Add your credentials to config.php

Open `config.php` in any text editor (Notepad, VS Code, etc.).

You will see this block:

```php
define('APP_URL',  'http://localhost/yard_fund_demo');
define('APP_NAME', 'The Yard Fund');

define('DB_HOST', 'localhost');
define('DB_NAME', 'yard_fund_demo');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
```

**What you need to change:**

| Setting | What to put |
|---------|-------------|
| `DB_USER` | Your phpMyAdmin username — almost always `root` on XAMPP, leave it |
| `DB_PASS` | Your phpMyAdmin password — almost always blank `''` on XAMPP, leave it |
| `SMTP_USER` | Your Gmail address e.g. `yourname@gmail.com` |
| `SMTP_PASS` | Your Gmail App Password (see Step 6 below) |

> `APP_URL`, `DB_HOST`, `DB_NAME`, `SMTP_HOST`, and `SMTP_PORT` do not need to change for local development.

---

## Step 6 — Create a Gmail App Password

The app sends emails for things like claim approvals, donation receipts, and password resets. It uses Gmail to do this. You need a special App Password — **not your regular Gmail password**.

1. Go to your Google Account:
   ```
   https://myaccount.google.com
   ```
2. Click **Security** in the left menu
3. Make sure **2-Step Verification is turned ON** — this is required before App Passwords will work. If it's off, turn it on first.
4. In the search bar at the top of the page, type **App passwords** and click it
5. Give it any name, for example `Yard Fund`
6. Click **Create**
7. Google will show you a **16-character password** like `abcd efgh ijkl mnop`
8. Copy that password
9. Paste it into `config.php` as the value for `SMTP_PASS`:
   ```php
   define('SMTP_PASS', 'abcd efgh ijkl mnop');
   ```
   Spaces are fine — paste it exactly as shown.
10. Save `config.php`

---

## Step 7 — Set the admin password

The admin account is already in the database but needs its password set on your machine.

1. Open your browser and go to:
   ```
   http://localhost/yard_fund_demo/fix_admin_password.php
   ```
2. You should see:
   ```
   ✅ Admin password reset to Admin1234!
   ```
3. **Delete `fix_admin_password.php` from your project folder immediately.**  
   Do not skip this — leaving it in is a security risk.

---

## Step 8 — Open the app

Go to:
```
http://localhost/yard_fund_demo/
```

You should see The Yard Fund landing page.

---

## Step 9 — Test everything works

Run through this quick checklist:

- [ ] Landing page loads
- [ ] Register a new student account → lands on student dashboard
- [ ] Register a new donor account → lands on donor dashboard
- [ ] Log in as admin: `admin@yardfund.com` / `Admin1234!` → lands on admin dashboard
- [ ] As student: submit a claim with a proof file
- [ ] As admin: approve the claim
- [ ] As donor: donate to the claim
- [ ] Check that confirmation emails arrive in your inbox

If emails are not arriving, double check your `SMTP_USER` and `SMTP_PASS` in `config.php` and make sure 2-Step Verification is on in your Google account.

---

## Admin Account

| Field    | Value                 |
|----------|-----------------------|
| Email    | `admin@yardfund.com`  |
| Password | `Admin1234!`          |

> Change your admin password after first login via **Account Settings**.

---

## Troubleshooting

**Page not found / blank page**
- Make sure Apache and MySQL are both running in the XAMPP Control Panel
- Make sure the folder is named exactly `yard_fund_demo` inside `htdocs`

**Database connection error**
- Make sure MySQL is running in XAMPP
- Check that `DB_USER` and `DB_PASS` in `config.php` match your phpMyAdmin login

**Emails not sending**
- Make sure you set `SMTP_USER` and `SMTP_PASS` in `config.php`
- Make sure 2-Step Verification is on in your Google account
- Make sure the App Password was copied correctly (16 characters)
- Check your Gmail Sent folder to see if emails went out

**Admin password not working**
- Make sure you visited `fix_admin_password.php` in the browser after importing the database
- If you re-ran `migration.sql`, you need to visit `fix_admin_password.php` again

**uploads/ folder errors**
- Make sure the `uploads` folder exists inside `yard_fund_demo`
- On Mac/Linux, make sure it has write permissions: `chmod 777 uploads`

---

## Notes for the Team

- Each person uses their **own** Gmail App Password in their own `config.php`
- Do not share or commit your `config.php` credentials
- The `uploads/` folder is not shared — each local environment has its own uploaded files
- If you re-run `migration.sql`, all data is wiped and you must run `fix_admin_password.php` again

---

*The Yard Fund — Est. 2026 · Virginia State University*
