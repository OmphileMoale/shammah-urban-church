<?php
/* ═══════════════════════════════════════
   includes_db.php  —  DATABASE CONNECTION

   SETUP (easiest):
   1. Open XAMPP → start Apache + MySQL
   2. Visit http://localhost/shammah-urban-church/install.php
   3. The installer builds the database and saves your
      connection details to includes/db_config.php

   MANUAL SETUP:
   Import db_setup.sql in phpMyAdmin, then edit the
   defaults below if your MySQL has a password.
═══════════════════════════════════════ */

/* Defaults for a standard XAMPP install */
$__db_cfg = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'shammah_db',
    'user' => 'root',
    'pass' => '',
];

/* install.php writes includes/db_config.php — it overrides the defaults */
$__db_cfg_file = __DIR__ . '/db_config.php';
if (is_file($__db_cfg_file)) {
    $__db_cfg = array_merge($__db_cfg, (array) include $__db_cfg_file);
}

define('DB_HOST', $__db_cfg['host']);
define('DB_PORT', (int) $__db_cfg['port']);
define('DB_NAME', $__db_cfg['name']);
define('DB_USER', $__db_cfg['user']);
define('DB_PASS', $__db_cfg['pass']);
define('DB_CHAR', 'utf8mb4');
unset($__db_cfg, $__db_cfg_file);

/* Try the configured port first, then the usual XAMPP ports (3306, 3307),
   so the site still works if it is copied to another computer. */
$pdo = null;
$e   = null;
try {
    foreach (array_values(array_unique([DB_PORT, 3306, 3307])) as $__port) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . $__port . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            break;                                   // connected
        } catch (PDOException $inner) {
            $e = $inner;
            /* 2002/2003 = nothing listening on this port → try the next one.
               Any other error (bad password, unknown database) → stop. */
            if (strpos($inner->getMessage(), '2002') === false
                && strpos($inner->getMessage(), '2003') === false) {
                break;
            }
        }
    }
    if ($pdo === null && $e !== null) {
        throw $e;
    }
} catch (PDOException $e) {
    http_response_code(503);

    /* AJAX / POST requests get JSON */
    $is_ajax = (
        $_SERVER['REQUEST_METHOD'] === 'POST' ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode([
            'ok'  => false,
            'msg' => 'Database connection failed. Make sure XAMPP MySQL is running and you have imported db_setup.sql.',
        ]));
    }

    /* Normal page loads get a helpful HTML guide */
    die('<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database Setup — Shammah Urban Church</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#f4f1ee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:640px;width:100%;overflow:hidden}
  .head{background:#3D0C2F;color:#fff;padding:2rem 2.5rem}
  .head h1{font-size:1.4rem;margin-bottom:.4rem}
  .head p{opacity:.8;font-size:.9rem}
  .body{padding:2.5rem}
  h2{font-size:.95rem;color:#3D0C2F;margin:1.5rem 0 .5rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
  h2:first-child{margin-top:0}
  ol{padding-left:1.4rem;line-height:2.1;color:#444}
  code{background:#f0edf4;color:#3D0C2F;padding:.1rem .4rem;border-radius:4px;font-size:.9rem}
  .note{background:#fef9e7;border-left:4px solid #C9A84C;padding:.75rem 1rem;border-radius:0 6px 6px 0;margin:.75rem 0;font-size:.9rem}
  .creds{background:#f4f1f7;border-radius:8px;padding:1rem 1.2rem;margin:.75rem 0}
  .creds table{width:100%;border-collapse:collapse}
  .creds td{padding:.3rem .6rem;font-size:.9rem;color:#444}
  .creds td:first-child{width:150px;color:#888}
  .err{background:#fdf2f2;border:1px solid #fcc;border-radius:6px;padding:.75rem 1rem;font-size:.82rem;color:#c0392b;margin-top:1.5rem;word-break:break-all}
</style>
</head>
<body>
<div class="card">
  <div class="head">
    <h1>⚙️ Database Setup Required</h1>
    <p>MySQL is not connected. Follow these 2 steps to fix it.</p>
  </div>
  <div class="body">
    <h2>Step 1 — Start XAMPP Services</h2>
    <ol>
      <li>Open the <strong>XAMPP Control Panel</strong></li>
      <li>Click <strong>Start</strong> next to <strong>Apache</strong></li>
      <li>Click <strong>Start</strong> next to <strong>MySQL</strong></li>
      <li>Both must turn <span style="color:green"><strong>green</strong></span></li>
    </ol>

    <h2>Step 2 — Run the Installer</h2>
    <ol>
      <li>Open <a href="install.php"><strong>install.php</strong></a> in this project</li>
      <li>Click <strong>Install Database Now</strong></li>
      <li>Come back and refresh this page</li>
    </ol>
    <div class="note">💡 Prefer phpMyAdmin? Import <code>db_setup.sql</code>, then run the installer once.</div>

    <h2>If MySQL Has a Password</h2>
    <p style="color:#444;font-size:.9rem">Enter it in the installer form — it is saved automatically.</p>

    <div class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>
  </div>
</div>
</body>
</html>');
}