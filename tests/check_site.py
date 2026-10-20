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
check("login verifies hash", "password_verify(" in read("login.php"))
check("login has lockout after failed attempts", "login_locked_" in read("login.php"))

# 4. No demo credentials leaked in the public DB error page
check("no hard-coded passwords in includes/db.php", "Admin@2025" not in read("includes/db.php"))

# 5. Required project files
for f in ("README.md", "db_setup.sql", "azure-pipelines.yml"):
    check(f"{f} present", os.path.exists(f))

sys.exit(1 if failed else 0)
