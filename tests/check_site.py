"""
Structural checks for the website (run by Azure Pipelines).
"""
import os, re, sys

failed = False
def check(name, ok):
    global failed
    failed |= not ok
    print(f"{'PASS' if ok else 'FAIL'}  {name}")

read = lambda p: open(p, encoding="utf-8").read()

# 1. Every page linked from the navigation bar exists
nav = read("includes/nav.php")
links = sorted(set(re.findall(r'href="([a-z_]+\.php)"', nav)))
for page in links:
    check(f"nav link target exists: {page}", os.path.exists(page))

# 2. Role guard + CSRF on the admin dashboard
dash = read("admin_dashboard.php")
check("dashboard restricted to admin/pastor", "['admin', 'pastor']" in dash)
check("dashboard checks CSRF token", "hash_equals" in dash)
check("dashboard uses prepared statements", "->prepare(" in dash)

# 3. Passwords are hashed, never stored plain
reg = read("register.php")
check("register hashes password (bcrypt)", "password_hash(" in reg and "PASSWORD_BCRYPT" in reg)
auth = read("includes/auth.php")
login = read("login.php")
check("login uses the shared sign-in check", "check_password_login(" in login)
check("shared sign-in verifies bcrypt hash", "password_verify(" in auth)
check("lockout after 3 failed attempts (stored in MEMBER)", "LOCK_AFTER_FAILS  = 3" in auth and "locked_until" in auth)
check("login sends a 6-digit code to email + phone", "otp_issue(" in login and "send_email(" in auth and "send_sms(" in auth)
check("codes are stored hashed, never plain", "password_hash($code" in auth)
check("app tokens are stored hashed", "hash('sha256', $token)" in auth)
check("forgot_password.php present and uses codes", "otp_issue(" in read("forgot_password.php"))

# 3b. App API: every signed-in endpoint checks the token, staff endpoints check the role
import glob
public_api = {"_bootstrap.php", "index.php", "register.php", "login.php", "code_login.php", "google_login.php",
              "verify_code.php", "resend_code.php", "forgot_password.php", "reset_password.php", "logout.php"}
for f in sorted(glob.glob("api/*.php")):
    name = os.path.basename(f)
    if name not in public_api:
        check(f"api/{name} requires sign-in token", "api_user($pdo)" in read(f))
for name in ("members.php", "prayer_status.php"):
    check(f"api/{name} restricted to admin/pastor", "api_require_staff(" in read(f"api/{name}"))

# 4. No demo credentials leaked in the public DB error page
check("no hard-coded passwords in includes/db.php", "Admin@2025" not in read("includes/db.php"))

# 5. Required project files
for f in ("README.md", "db_setup.sql", "db_update_shared_login.sql", "azure-pipelines.yml"):
    check(f"{f} present", os.path.exists(f))

sys.exit(1 if failed else 0)
