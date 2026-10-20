"""
Automated checks for db_setup.sql (run by Azure Pipelines).
  1. Every table that is seeded has at least 10 records.
  2. Every table that is created has seed data.
  3. Passwords are stored as bcrypt hashes (never plain text).
"""
import re, sys

MIN_ROWS = 10
sql = open("db_setup.sql", encoding="utf-8").read()

def count_rows(values_text):
    """Count top-level (...) tuples, ignoring parentheses inside quoted strings."""
    depth, rows, quote, i = 0, 0, None, 0
    while i < len(values_text):
        c = values_text[i]
        if quote:
            if c == "\\":
                i += 1
            elif c == quote:
                if values_text[i + 1:i + 2] == quote:
                    i += 1
                else:
                    quote = None
        elif c in ("'", '"'):
            quote = c
        elif c == "-" and values_text[i:i + 2] == "--":
            i = values_text.find("\n", i)
            if i == -1:
                break
        elif c == "(":
            if depth == 0:
                rows += 1
            depth += 1
        elif c == ")":
            depth -= 1
        elif c == ";" and depth == 0:
            break
        i += 1
    return rows

created = re.findall(r"CREATE TABLE IF NOT EXISTS (\w+)", sql)
counts = {t: 0 for t in created}
for m in re.finditer(r"INSERT INTO (\w+)[^;]*?VALUES", sql, re.S | re.I):
    table = m.group(1)
    counts[table] = counts.get(table, 0) + count_rows(sql[m.end():])

failed = False
for table in created:
    n = counts.get(table, 0)
    ok = n >= MIN_ROWS
    failed |= not ok
    print(f"{'PASS' if ok else 'FAIL'}  {table:<16} {n} records (need {MIN_ROWS})")

hashes = re.findall(r"'(\$2y\$\d\d\$[A-Za-z0-9./]{53})'", sql)
ok = len(hashes) >= 2
failed |= not ok
print(f"{'PASS' if ok else 'FAIL'}  bcrypt password hashes present ({len(hashes)} found)")

sys.exit(1 if failed else 0)
