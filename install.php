<?php
/* ═══════════════════════════════════════════════════════════
   install.php  —  Shammah Urban Church Installer

   WHAT IT DOES
   1. Checks your PHP version and the PDO MySQL extension
   2. Connects to MySQL with the details you type in
   3. Creates the database and runs db_setup.sql
      (the SQL file is the single source of truth)
   4. Creates the two demo logins with password_hash()
      and PROVES they work with password_verify()
   5. Saves your connection details to includes/db_config.php
   6. Locks itself (install.lock) so it cannot be re-run

   AFTER IT SUCCEEDS: delete install.php and install.lock.
═══════════════════════════════════════════════════════════ */

session_start();

define('SQL_FILE',  __DIR__ . '/db_setup.sql');
define('LOCK_FILE', __DIR__ . '/install.lock');
define('CFG_FILE',  __DIR__ . '/includes/db_config.php');

/* Demo accounts — passwords are hashed by PHP at install time */
$DEMO_ACCOUNTS = [
    'admin@shammah.co.za' => 'Admin@2025',
    'thabo@email.com'     => 'Member@2025',
];

/* Tables we expect after a successful install */
$EXPECTED_TABLES = [
    'MEMBER', 'ADDRESS', 'EVENT', 'RSVP', 'ATTENDANCE', 'DONATION',
    'MINISTRY', 'MINISTRY_MEMBER', 'VOLUNTEER', 'ANNOUNCEMENT', 'CONTACT_LOG',
];

/* ── Helper: split an SQL file into single statements ─────
   Respects quoted strings ('' and \' inside them) and
   skips "-- comment" lines, so semicolons inside text are safe. */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $quote      = null;
    $len        = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        /* inside a quoted string */
        if ($quote !== null) {
            $buffer .= $c;
            if ($c === '\\' && $i + 1 < $len) {   // escaped character
                $buffer .= $sql[++$i];
                continue;
            }
            if ($c === $quote) {                  // closing quote
                $quote = null;
            }
            continue;
        }

        /* opening quote */
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote   = $c;
            $buffer .= $c;
            continue;
        }

        /* "-- comment" runs to the end of the line */
        if ($c === '-' && ($sql[$i + 1] ?? '') === '-'
            && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\r", "\n"], true)) {
            $nl = strpos($sql, "\n", $i);
            if ($nl === false) {
                break;                            // comment at end of file
            }
            $i       = $nl;                       // skip to the newline
            $buffer .= "\n";
            continue;
        }

        /* end of statement */
        if ($c === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $c;
    }

    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

$errors    = [];
$success   = [];
$done      = false;
$installed = is_file(LOCK_FILE);
$values    = ['host' => '127.0.0.1', 'port' => 3307, 'user' => 'root', 'name' => 'shammah_db'];

/* CSRF token for the installer form */
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}

/* ── ENVIRONMENT CHECKS ───────────────────────────────── */
$env_ok = true;
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = 'PHP 7.4 or newer is required. You are running PHP ' . PHP_VERSION . '. Update XAMPP.';
    $env_ok   = false;
}
if (!extension_loaded('pdo_mysql')) {
    $errors[] = 'The PDO MySQL extension is not enabled. In XAMPP open php.ini and enable extension=pdo_mysql.';
    $env_ok   = false;
}
if (!is_file(SQL_FILE)) {
    $errors[] = 'db_setup.sql was not found next to install.php. Keep both files in the project root.';
    $env_ok   = false;
}

/* ═══════════════════════════════════════════════════════
   FORM HANDLER
═══════════════════════════════════════════════════════ */
if (!$installed && $env_ok && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $values['host'] = trim($_POST['db_host'] ?? '127.0.0.1') ?: '127.0.0.1';
    $values['port'] = (int) ($_POST['db_port'] ?? 3307);
    $values['user'] = trim($_POST['db_user'] ?? 'root')      ?: 'root';
    $values['name'] = trim($_POST['db_name'] ?? 'shammah_db') ?: 'shammah_db';
    $db_pass        = $_POST['db_pass'] ?? '';

    $csrf_ok = isset($_POST['csrf'])
               && hash_equals($_SESSION['install_csrf'], (string) $_POST['csrf']);

    if (!$csrf_ok) {
        $errors[] = 'Security token mismatch. Refresh the page and try again.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $values['name'])) {
        $errors[] = 'Database name may only contain letters, numbers and underscores.';
    } elseif ($values['port'] < 1 || $values['port'] > 65535) {
        $errors[] = 'Port must be a number between 1 and 65535.';
    } else {
        try {
            /* STEP 1 — connect WITHOUT selecting a database.
               Tries the port you typed first, then the two usual XAMPP
               ports (3306, 3307) if MySQL refuses the connection. */
            $pdo      = null;
            $last_err = null;
            foreach (array_values(array_unique([$values['port'], 3306, 3307])) as $try_port) {
                try {
                    $pdo = new PDO(
                        'mysql:host=' . $values['host'] . ';port=' . $try_port . ';charset=utf8mb4',
                        $values['user'],
                        $db_pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $values['port'] = $try_port;
                    break;
                } catch (PDOException $e) {
                    $last_err = $e;
                    /* 2002/2003 = nothing listening on that port: try the next.
                       Anything else (e.g. wrong password) means we reached MySQL: stop. */
                    if (strpos($e->getMessage(), '2002') === false
                        && strpos($e->getMessage(), '2003') === false) {
                        break;
                    }
                }
            }
            if ($pdo === null) {
                throw $last_err;
            }
            $success[] = 'Connected to MySQL on port <strong>' . (int) $values['port']
                       . '</strong> as <strong>' . htmlspecialchars($values['user']) . '</strong>.';

            /* STEP 2 — create + select the database */
            $db = '`' . $values['name'] . '`';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$db}
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE {$db}");
            $success[] = 'Database <strong>' . htmlspecialchars($values['name']) . '</strong> is ready.';

            /* STEP 3 — run db_setup.sql statement by statement */
            $statements   = split_sql((string) file_get_contents(SQL_FILE));
            $seed_checked = false;
            $do_seed      = true;
            $seeded       = false;

            foreach ($statements as $n => $stmt) {
                preg_match('/^\s*(\w+)(?:\s+(\w+))?/', $stmt, $m);
                $verb = strtoupper($m[1] ?? '');
                $obj  = strtoupper($m[2] ?? '');

                /* The installer already created/selected the DB itself */
                if ($verb === 'USE' || ($verb === 'CREATE' && $obj === 'DATABASE')) {
                    continue;
                }

                /* INSERT / UPDATE = seed data. Only run on an empty database. */
                if ($verb === 'INSERT' || $verb === 'UPDATE') {
                    if (!$seed_checked) {
                        $existing     = (int) $pdo->query('SELECT COUNT(*) FROM MEMBER')->fetchColumn();
                        $do_seed      = ($existing === 0);
                        $seed_checked = true;
                        if (!$do_seed) {
                            $success[] = 'Sample data skipped — database already has '
                                       . $existing . ' member(s).';
                        }
                    }
                    if (!$do_seed) {
                        continue;
                    }
                    $seeded = true;
                }

                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    throw new PDOException(
                        'Statement #' . ($n + 1) . ' in db_setup.sql failed: ' . $e->getMessage()
                    );
                }

                if ($verb === 'CREATE' && $obj === 'TABLE'
                    && preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $stmt, $t)) {
                    $success[] = 'Table <strong>' . htmlspecialchars($t[1]) . '</strong> ready.';
                }
            }
            if ($seeded) {
                $success[] = 'Sample data inserted (events are dated relative to today).';
            }

            /* STEP 4 — (re)create demo passwords with PHP, then verify them */
            $upd = $pdo->prepare('UPDATE MEMBER SET password_hash = ? WHERE email = ?');
            foreach ($DEMO_ACCOUNTS as $email => $plain) {
                $upd->execute([password_hash($plain, PASSWORD_DEFAULT), $email]);
            }
            $sel = $pdo->prepare('SELECT password_hash FROM MEMBER WHERE email = ?');
            foreach ($DEMO_ACCOUNTS as $email => $plain) {
                $sel->execute([$email]);
                $hash = $sel->fetchColumn();
                if ($hash && password_verify($plain, $hash)) {
                    $success[] = 'Self-test: <strong>' . htmlspecialchars($email) . '</strong> password verifies.';
                } else {
                    $errors[] = 'Self-test FAILED for ' . $email . ' — demo account missing or hash invalid.';
                }
            }

            /* STEP 5 — self-test: every expected table exists and is queryable */
            foreach ($EXPECTED_TABLES as $table) {
                try {
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                    $success[] = "Self-test: <strong>{$table}</strong> OK ({$count} row" . ($count === 1 ? '' : 's') . ').';
                } catch (PDOException $e) {
                    $errors[] = "Self-test FAILED: table {$table} is missing.";
                }
            }

            /* STEP 6 — save connection details for includes/db.php */
            if (empty($errors)) {
                $cfg = "<?php\n"
                     . "// Generated by install.php. Do not publish this file.\n"
                     . 'return ' . var_export([
                           'host' => $values['host'],
                           'port' => $values['port'],
                           'name' => $values['name'],
                           'user' => $values['user'],
                           'pass' => $db_pass,
                       ], true) . ";\n";

                if (@file_put_contents(CFG_FILE, $cfg) !== false) {
                    $success[] = 'Connection details saved to <code>includes/db_config.php</code>.';
                } else {
                    $errors[] = 'Could not write includes/db_config.php. Edit includes/db.php and set the details by hand.';
                }
            }

            /* STEP 7 — lock the installer */
            if (empty($errors)) {
                @file_put_contents(LOCK_FILE, 'Installed ' . date('c') . "\n");
                $done      = true;
                $success[] = 'Installation complete!';
                unset($_SESSION['install_csrf']);
            }

        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
            if (strpos($e->getMessage(), '1045') !== false) {
                $errors[] = 'Hint: wrong MySQL username or password. XAMPP default is user "root" with an empty password.';
            } elseif (strpos($e->getMessage(), '2002') !== false) {
                $errors[] = 'Hint: MySQL is not running. Start it in the XAMPP Control Panel.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Install — Shammah Urban Church</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#f4f1ee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .card{background:#fff;border-radius:14px;box-shadow:0 6px 32px rgba(0,0,0,.13);max-width:600px;width:100%;overflow:hidden}
  .head{background:#3D0C2F;color:#fff;padding:2rem 2.5rem;display:flex;align-items:center;gap:1rem}
  .head-logo{font-size:1.4rem;font-weight:900;background:rgba(255,255,255,.15);border-radius:10px;width:52px;height:52px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .head h1{font-size:1.25rem;margin-bottom:.2rem}
  .head p{opacity:.75;font-size:.88rem}
  .body{padding:2rem 2.5rem}
  .info-box{background:#f0edf4;border-left:4px solid #3D0C2F;padding:.9rem 1.1rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem;font-size:.9rem;color:#444;line-height:1.7}
  .field{margin-bottom:1rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  label{display:block;font-size:.85rem;font-weight:600;color:#555;margin-bottom:.4rem}
  input[type=password],input[type=text]{width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;transition:border .2s}
  input:focus{border-color:#3D0C2F}
  .hint{font-size:.8rem;color:#888;margin-top:.3rem}
  .btn{width:100%;padding:.9rem;background:#3D0C2F;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s;margin-top:.5rem}
  .btn:hover{background:#5a1245}
  .messages{margin-bottom:1.5rem;max-height:340px;overflow-y:auto}
  .msg{padding:.5rem 1rem;border-radius:8px;font-size:.86rem;margin-bottom:.4rem;line-height:1.5}
  .msg.ok{background:#eafaf1;color:#1a7a40;border:1px solid #b7e4c7}
  .msg.err{background:#fdf2f2;color:#c0392b;border:1px solid #f5c6cb}
  .msg.final{background:#3D0C2F;color:#fff;font-size:1rem;font-weight:600;padding:1rem;text-align:center}
  .creds{background:#f4f1f7;border-radius:10px;padding:1.2rem 1.5rem;margin-top:1.2rem}
  .creds h3{font-size:.9rem;color:#3D0C2F;margin-bottom:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
  .creds table{width:100%;border-collapse:collapse}
  .creds td{padding:.3rem .4rem;font-size:.9rem;color:#444}
  .creds td:first-child{color:#888;width:140px}
  code{background:#e8e0f0;color:#3D0C2F;padding:.1rem .4rem;border-radius:4px;font-size:.88rem}
  .login-btn{display:block;text-align:center;margin-top:1.2rem;padding:.8rem;background:#C9A84C;color:#fff;border-radius:8px;font-weight:700;text-decoration:none;font-size:.95rem}
  .login-btn:hover{background:#b8923e}
  .delete-warn{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:.8rem 1rem;margin-top:1rem;font-size:.82rem;color:#7a5c00}
</style>
</head>
<body>
<div class="card">
  <div class="head">
    <div class="head-logo">SUC</div>
    <div>
      <h1>Database Installer</h1>
      <p>Shammah Urban Church — one-click setup</p>
    </div>
  </div>
  <div class="body">

  <?php if ($installed && !$done): ?>

    <div class="msg ok">Already installed.</div>
    <div class="delete-warn">
      The installer is locked so nobody can re-run it. To install again, delete
      <code>install.lock</code>. When you are finished, delete <code>install.php</code> too.
    </div>
    <a href="login.php" class="login-btn">→ Go to Login Page</a>

  <?php elseif (!empty($success) || !empty($errors)): ?>

    <div class="messages">
      <?php foreach ($success as $msg): ?>
        <div class="msg <?= $msg === 'Installation complete!' ? 'final' : 'ok' ?>"><?= $msg ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $msg): ?>
        <div class="msg err"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($done): ?>
      <div class="creds">
        <h3>Demo Logins</h3>
        <table>
          <tr><td>Admin email</td><td><code>admin@shammah.co.za</code></td></tr>
          <tr><td>Admin password</td><td><code>Admin@2025</code></td></tr>
          <tr><td>Member email</td><td><code>thabo@email.com</code></td></tr>
          <tr><td>Member password</td><td><code>Member@2025</code></td></tr>
        </table>
      </div>
      <a href="login.php" class="login-btn">→ Go to Login Page</a>
      <div class="delete-warn">
        <strong>Security:</strong> delete <code>install.php</code> and <code>install.lock</code>
        from the project folder once you have logged in, and change the demo passwords.
      </div>
    <?php elseif ($env_ok): ?>
      <a href="install.php" class="login-btn" style="background:#3D0C2F">← Try again</a>
    <?php endif; ?>

  <?php elseif ($env_ok): ?>

    <div class="info-box">
      This will automatically:<br>
      • Create the database and all tables from <code>db_setup.sql</code><br>
      • Insert sample data and two demo logins<br>
      • Test that the logins and tables work<br>
      • Save your connection details and lock itself<br><br>
      Make sure <strong>Apache + MySQL are running</strong> in XAMPP first.
    </div>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
      <div class="row">
        <div class="field">
          <label for="db_host">MySQL host</label>
          <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($values['host']) ?>">
        </div>
        <div class="field">
          <label for="db_port">MySQL port</label>
          <input type="text" id="db_port" name="db_port" inputmode="numeric" value="<?= (int) $values['port'] ?>">
          <div class="hint">XAMPP uses 3306 or 3307. The installer tries both.</div>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label for="db_user">MySQL user</label>
          <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($values['user']) ?>">
        </div>
        <div class="field">
          <label for="db_name">Database name</label>
          <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($values['name']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="db_pass">MySQL password</label>
        <input type="password" id="db_pass" name="db_pass" placeholder="Leave blank if XAMPP default (no password)">
        <div class="hint">Most XAMPP installations have no password — just leave this empty.</div>
      </div>
      <button class="btn" type="submit">⚡ Install Database Now</button>
    </form>

  <?php else: ?>

    <?php foreach ($errors as $msg): ?>
      <div class="msg err"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

  <?php endif; ?>

  </div>
</div>
</body>
</html>