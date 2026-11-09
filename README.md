# Shammah Urban Church – CMES Website

Church Management and Engagement System (CMES): the website companion to the
Shammah Urban Church Android app.
Module XISD6329 · Task 2 · CodeVision Team · Rosebank College (IIE)

**Team:** Omphile Moale (ST10455873), Oladele Rakgolela (ST10449832), Lebo Ramoshaba (ST10453111)
**Client:** Shammah Urban Church, Pretoria

## What is in this repository
| Part | Technology |
|---|---|
| Website | PHP 8, MySQL (PDO), HTML, CSS, JavaScript |
| Android app | Kotlin (separate project, see the user documentation) |
| Automation | Azure Pipelines (`azure-pipelines.yml`) and `tests/` |

## Features (Task 2)
- Public pages: Home, About, Events (RSVP), Sermons, Ministries, Give, Prayer Wall, Gallery, Contact, Privacy, Terms
- Register and login with **email or phone number**. Passwords are hashed with bcrypt (`password_hash`). Login locks for 15 minutes after 3 failed attempts (stored in the database, so the lock also applies in the app)
- **6-digit sign-in code** sent to the member's email (Gmail SMTP) and phone (BulkSMS) after the password, on registration, and for "Forgot password" (`forgot_password.php`)
- **Shared database with the Android app:** `api/` gives the app JSON access to the same `shammah_db` (accounts, events/RSVP, donations, prayer requests, ministries, member list). See `SETUP_SHARED_DATABASE.md`
- Role-based access: admin, pastor, member. Admin and pastor use `admin_dashboard.php`, members use `member_portal.php`
- Admin/Pastor dashboard: key figures, member directory (activate/deactivate), donation report, prayer request approval, contact messages
- Member portal: profile, RSVPs, donations, volunteering, password change
- Security: CSRF tokens, prepared statements (PDO), output escaping, session regeneration on login

## Setup (XAMPP on Windows)
1. Copy this folder to `C:\xampp\htdocs\shammah-urban-church\`
2. Start **Apache** and **MySQL** in the XAMPP Control Panel
3. Open http://localhost/phpmyadmin, create a database named `shammah_db`
4. Import `db_setup.sql` into `shammah_db` (creates all tables and sample data), then import `db_update_shared_login.sql` (sign-in codes, app tokens, shared lockout)
5. If MySQL has a password, or doesn't use port 3307, set it in `includes/db.php`
5b. To send real email, create a Gmail App Password for `babatundesaliu19@gmail.com`, set `SHAMMAH_SMTP_PASS` in Apache's environment, then restart Apache. Contact form messages are sent to this inbox; submissions report an error rather than showing success if email delivery fails. For XAMPP, add `SetEnv SHAMMAH_SMTP_PASS "your-16-character-app-password"` to `C:\xampp\apache\conf\httpd.conf`. `SHAMMAH_SMTP_USER` defaults to the contact inbox. Keep the app password private and out of source control.
5c. To send sign-in codes by SMS, fill in BulkSMS details in `includes/notify_config.php`. Until email/SMS is configured, login remains in test mode.
6. Open http://localhost/shammah-urban-church/

## Demo logins (sample data only)
| Role | Email | Password |
|---|---|---|
| Admin | admin@shammah.co.za | Admin@2025 |
| Pastor | pastor@shammah.co.za | Admin@2025 |
| Member | thabo@email.com | Member@2025 |

Remove these demo accounts before any real release.

## Database
14 tables, each with at least 10 sample records:
MEMBER, ADDRESS, EVENT, RSVP, ATTENDANCE, DONATION, MINISTRY, MINISTRY_MEMBER,
VOLUNTEER, ANNOUNCEMENT, SERMON, PRAYER_REQUEST, GALLERY, CONTACT_MESSAGE.

`db_update_shared_login.sql` adds two working tables, filled in while people use the site and app:
OTP_CODE (hashed 6-digit codes, 10-minute expiry) and API_TOKEN (hashed app sign-in tokens, 30 days).
It also adds MEMBER.failed_logins / MEMBER.locked_until.

## Automated build and tests
`azure-pipelines.yml` runs on every push to `main`:
1. **Build:** `php -l` on every PHP file
2. **Test:** `tests/check_database.py` (10+ records per table, bcrypt hashes)
3. **Test:** `tests/check_site.py` (nav links, role guard, CSRF, hashing, lockout, sign-in codes, API token checks)

Run locally: `python3 tests/check_database.py && python3 tests/check_site.py`

## Azure DevOps
- Repos: source control with regular commits from all members
- Boards: task allocation and progress
- Pipelines: automated build and test (above)

## Planned for Task 3
PayFast online payments and recurring giving, annual giving statement, bulk Email/SMS/WhatsApp messaging, QR check-in,
children's check-in, room booking, visitor follow-up pipeline, reports centre,
backup and restore, dark mode, multi-language and PWA support.
The Android app features planned for Task 3 are biometric sign-in, multi-language,
QR check-in, PayFast, low-data mode, giving statement, reading plans and audit log.

## Known limitations
- Sermons, Prayer Wall submission and Gallery pages still read from PHP arrays in
  `includes/data.php`; their database tables are in place and used by the dashboard
- `install.php` is the older installer; use `db_setup.sql`
