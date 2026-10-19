<?php
/* ═══════════════════════════════════════
   includes/db.php  —  DATABASE CONNECTION

   SETUP:
   1. Open XAMPP → start Apache + MySQL
   2. Go to http://localhost/phpmyadmin
   3. Create database:  shammah_db
   4. Import:           db_setup.sql
   5. If MySQL has a password, update DB_PASS below
═══════════════════════════════════════ */

define('DB_HOST', 'localhost');
define('DB_NAME', 'shammah_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // ← change if your MySQL has a password
define('DB_CHAR', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
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
    <p>MySQL is not connected. Follow these 4 steps to fix it.</p>
  </div>
  <div class="body">
    <h2>Step 1 — Start XAMPP Services</h2>
    <ol>
      <li>Open the <strong>XAMPP Control Panel</strong></li>
      <li>Click <strong>Start</strong> next to <strong>Apache</strong></li>
      <li>Click <strong>Start</strong> next to <strong>MySQL</strong></li>
      <li>Both must turn <span style="color:green"><strong>green</strong></span></li>
    </ol>

    <h2>Step 2 — Create the Database</h2>
    <ol>
      <li>Open <a href="http://localhost/phpmyadmin" target="_blank"><strong>http://localhost/phpmyadmin</strong></a></li>
      <li>Click <strong>New</strong> in the left sidebar</li>
      <li>Type <code>shammah_db</code> as the name → click <strong>Create</strong></li>
    </ol>

    <h2>Step 3 — Import the SQL File</h2>
    <ol>
      <li>Click <code>shammah_db</code> in the left sidebar</li>
      <li>Click the <strong>Import</strong> tab at the top</li>
      <li>Click <strong>Choose File</strong> → select <code>db_setup.sql</code> from your project folder</li>
      <li>Scroll down → click <strong>Go</strong></li>
    </ol>
    <div class="note">💡 <code>db_setup.sql</code> is in your <code>shammah-urban-church</code> project folder root.</div>

    <h2>Step 4 — Test Login</h2>
    <div class="creds">
      <table>
        <tr><td>Admin email</td><td><code>admin@shammah.co.za</code></td></tr>
        <tr><td>Admin password</td><td><code>Admin@2025</code></td></tr>
        <tr><td>Member email</td><td><code>thabo@email.com</code></td></tr>
        <tr><td>Member password</td><td><code>Member@2025</code></td></tr>
      </table>
    </div>

    <h2>If MySQL Has a Password</h2>
    <p style="color:#444;font-size:.9rem">Edit <code>includes/db.php</code> line: <code>define(\'DB_PASS\', \'\');</code> → add your password between the quotes.</p>

    <div class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>
  </div>
</div>
</body>
</html>');
}
