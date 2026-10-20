<?php
/* ═══════════════════════════════════════════════
   install.php  —  Shammah Urban Church Installer
   
   DROP THIS FILE in your shammah-urban-church folder.
   Open http://localhost/shammah-urban-church/install.php
   It creates the database and all tables automatically.
   DELETE this file after it succeeds.
═══════════════════════════════════════════════ */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';        // ← add your MySQL password here if you have one
$DB_NAME = 'shammah_db';

$errors  = [];
$success = [];
$pdo     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Allow custom password from the form */
    $DB_PASS = $_POST['db_pass'] ?? '';

    try {
        /* Connect WITHOUT selecting a database first */
        $pdo = new PDO(
            "mysql:host={$DB_HOST};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        /* ── STEP 1: Create database ── */
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}`
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$DB_NAME}`");
        $success[] = "✅ Database <strong>shammah_db</strong> created (or already exists).";

        /* ── STEP 2: Create all tables ── */
        $tables = [

            "MEMBER" => "CREATE TABLE IF NOT EXISTS MEMBER (
                member_id       INT           NOT NULL AUTO_INCREMENT,
                title           VARCHAR(10)   NOT NULL DEFAULT 'Mr',
                first_name      VARCHAR(60)   NOT NULL,
                last_name       VARCHAR(60)   NOT NULL,
                email           VARCHAR(120)  NOT NULL UNIQUE,
                phone           VARCHAR(20)   NOT NULL DEFAULT '',
                date_of_birth   DATE          NULL,
                gender          VARCHAR(10)   NOT NULL DEFAULT 'Other',
                membership_type VARCHAR(40)   NOT NULL DEFAULT 'Guest',
                password_hash   VARCHAR(255)  NOT NULL,
                role            VARCHAR(20)   NOT NULL DEFAULT 'member',
                is_active       TINYINT(1)    NOT NULL DEFAULT 1,
                created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (member_id),
                INDEX idx_email (email),
                INDEX idx_role  (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "ADDRESS" => "CREATE TABLE IF NOT EXISTS ADDRESS (
                address_id  INT          NOT NULL AUTO_INCREMENT,
                member_id   INT          NOT NULL,
                street      VARCHAR(120) NOT NULL DEFAULT '',
                city        VARCHAR(60)  NOT NULL DEFAULT '',
                postal_code VARCHAR(10)  NOT NULL DEFAULT '',
                PRIMARY KEY (address_id),
                UNIQUE KEY uq_member (member_id),
                FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "EVENT" => "CREATE TABLE IF NOT EXISTS EVENT (
                event_id    INT          NOT NULL AUTO_INCREMENT,
                title       VARCHAR(120) NOT NULL,
                event_date  DATETIME     NOT NULL,
                day_str     VARCHAR(4)   NOT NULL DEFAULT '',
                month_str   VARCHAR(4)   NOT NULL DEFAULT '',
                time_str    VARCHAR(40)  NOT NULL DEFAULT '',
                venue       VARCHAR(100) NOT NULL DEFAULT '',
                capacity    INT          NOT NULL DEFAULT 100,
                color       VARCHAR(10)  NOT NULL DEFAULT '#3D0C2F',
                category    VARCHAR(40)  NOT NULL DEFAULT 'General',
                description TEXT         NULL,
                is_active   TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (event_id),
                INDEX idx_date (event_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "RSVP" => "CREATE TABLE IF NOT EXISTS RSVP (
                rsvp_id    INT         NOT NULL AUTO_INCREMENT,
                member_id  INT         NOT NULL,
                event_id   INT         NOT NULL,
                status     VARCHAR(20) NOT NULL DEFAULT 'confirmed',
                created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (rsvp_id),
                UNIQUE KEY uq_member_event (member_id, event_id),
                FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE,
                FOREIGN KEY (event_id)  REFERENCES EVENT(event_id)   ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "ATTENDANCE" => "CREATE TABLE IF NOT EXISTS ATTENDANCE (
                attendance_id INT         NOT NULL AUTO_INCREMENT,
                member_id     INT         NOT NULL,
                event_id      INT         NOT NULL,
                status        VARCHAR(20) NOT NULL DEFAULT 'present',
                PRIMARY KEY (attendance_id),
                UNIQUE KEY uq_att (member_id, event_id),
                FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE,
                FOREIGN KEY (event_id)  REFERENCES EVENT(event_id)   ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "DONATION" => "CREATE TABLE IF NOT EXISTS DONATION (
                donation_id     INT           NOT NULL AUTO_INCREMENT,
                member_id       INT           NOT NULL,
                amount          DECIMAL(10,2) NOT NULL,
                donation_type   VARCHAR(40)   NOT NULL DEFAULT 'Offering',
                payment_method  VARCHAR(20)   NOT NULL DEFAULT 'Cash',
                reference       VARCHAR(60)   NOT NULL DEFAULT '',
                donation_date   DATE          NOT NULL,
                PRIMARY KEY (donation_id),
                INDEX idx_member (member_id),
                FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "MINISTRY" => "CREATE TABLE IF NOT EXISTS MINISTRY (
                ministry_id  INT          NOT NULL AUTO_INCREMENT,
                name         VARCHAR(80)  NOT NULL,
                category     VARCHAR(30)  NOT NULL,
                icon         VARCHAR(30)  NOT NULL DEFAULT 'ti-star',
                color        VARCHAR(10)  NOT NULL DEFAULT '#3D0C2F',
                leader       VARCHAR(80)  NOT NULL DEFAULT '',
                schedule     VARCHAR(120) NOT NULL DEFAULT '',
                desc_short   VARCHAR(160) NOT NULL DEFAULT '',
                desc_full    TEXT         NULL,
                testimonial  TEXT         NULL,
                is_active    TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (ministry_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "MINISTRY_MEMBER" => "CREATE TABLE IF NOT EXISTS MINISTRY_MEMBER (
                id          INT        NOT NULL AUTO_INCREMENT,
                member_id   INT        NOT NULL,
                ministry_id INT        NOT NULL,
                date_joined DATE       NOT NULL DEFAULT (CURDATE()),
                is_active   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uq_mm (member_id, ministry_id),
                FOREIGN KEY (member_id)   REFERENCES MEMBER(member_id)   ON DELETE CASCADE,
                FOREIGN KEY (ministry_id) REFERENCES MINISTRY(ministry_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "VOLUNTEER" => "CREATE TABLE IF NOT EXISTS VOLUNTEER (
                volunteer_id  INT          NOT NULL AUTO_INCREMENT,
                member_id     INT          NOT NULL,
                ministry_name VARCHAR(80)  NOT NULL,
                role_name     VARCHAR(80)  NOT NULL DEFAULT '',
                status        VARCHAR(20)  NOT NULL DEFAULT 'interested',
                start_date    DATE         NULL,
                hours         DECIMAL(6,1) NOT NULL DEFAULT 0,
                PRIMARY KEY (volunteer_id),
                FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "ANNOUNCEMENT" => "CREATE TABLE IF NOT EXISTS ANNOUNCEMENT (
                announcement_id INT          NOT NULL AUTO_INCREMENT,
                title           VARCHAR(120) NOT NULL,
                body            TEXT         NOT NULL,
                icon            VARCHAR(30)  NOT NULL DEFAULT 'ti-info-circle',
                color           VARCHAR(20)  NOT NULL DEFAULT 'navy',
                created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_active       TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (announcement_id),
                INDEX idx_active (is_active, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $name => $sql) {
            $pdo->exec($sql);
            $success[] = "✅ Table <strong>{$name}</strong> ready.";
        }

        /* ── STEP 3: Seed data (skip if already exists) ── */
        $existing = (int) $pdo->query("SELECT COUNT(*) FROM MEMBER")->fetchColumn();

        if ($existing === 0) {

            /* Members */
            $pdo->exec("INSERT INTO MEMBER
                (title,first_name,last_name,email,phone,date_of_birth,gender,membership_type,password_hash,role,is_active,created_at)
                VALUES
                ('Mr','Admin','Shammah','admin@shammah.co.za','012 345 6789','1985-01-01','Male','Full Member',
                 '\$2y\$12\$prXP3CpPlnNHDAhy1xCdTuzE7ziBjpApXpGtKUpHUY6WoejdJyqKy','admin',1,'2021-01-01 08:00:00'),
                ('Mr','Thabo','Nkosi','thabo@email.com','071 234 5678','1995-03-15','Male','Full Member',
                 '\$2y\$12\$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2021-03-15 10:00:00')");

            /* Addresses */
            $pdo->exec("INSERT INTO ADDRESS (member_id,street,city,postal_code) VALUES
                (1,'1103 Eland Street, Mooikloof','Pretoria East','0059'),
                (2,'1103 Eland Street, Mooikloof','Pretoria East','0059')");

            /* Events — use future dates so portal RSVP widget shows them */
            $pdo->exec("INSERT INTO EVENT (title,event_date,day_str,month_str,time_str,venue,capacity,color,category,description) VALUES
                ('Sunday Morning Service',   DATE_ADD(NOW(), INTERVAL 7  DAY),'--','---','9:00 AM',          'Main Sanctuary',       500,'#3D0C2F','Worship', 'Join us for our Sunday morning worship service.'),
                ('Youth Camp 2025',          DATE_ADD(NOW(), INTERVAL 14 DAY),'--','---','Fri-Sun Weekend',  'Hartbeespoort Resort',  120,'#639922','Youth',   'An unforgettable weekend for young people aged 13-25.'),
                ('Community Prayer Night',   DATE_ADD(NOW(), INTERVAL 10 DAY),'--','---','7:00 PM',          'Fellowship Hall',       150,'#378ADD','Outreach','A powerful evening of corporate intercession.'),
                ('Women of Purpose Meeting', DATE_ADD(NOW(), INTERVAL 21 DAY),'--','---','10:00 AM',         'Conference Room B',     100,'#C9A84C','Women',   'Our monthly Women of Purpose gathering.'),
                ('Children Ministry Fun Day',DATE_ADD(NOW(), INTERVAL 28 DAY),'--','---','9:00 AM - 1:00 PM','Church Grounds',         80,'#C0392B','Children','A special Saturday morning for children aged 4-12.')");

            /* RSVPs */
            $pdo->exec("INSERT INTO RSVP (member_id,event_id,status) VALUES (2,2,'confirmed'),(2,3,'confirmed')");

            /* Attendance */
            $pdo->exec("INSERT INTO ATTENDANCE (member_id,event_id,status) VALUES (2,1,'present'),(2,3,'present')");

            /* Donations */
            $pdo->exec("INSERT INTO DONATION (member_id,amount,donation_type,payment_method,reference,donation_date) VALUES
                (2,2500.00,'Tithe',        'EFT', 'TH-20250501','2025-05-01'),
                (2,2500.00,'Tithe',        'EFT', 'TH-20250401','2025-04-01'),
                (2,1000.00,'Building Fund','Card','BF-20250315','2025-03-15'),
                (2,2500.00,'Tithe',        'EFT', 'TH-20250301','2025-03-01'),
                (2, 500.00,'Offering',     'Cash','OF-20250210','2025-02-10'),
                (2,2500.00,'Tithe',        'EFT', 'TH-20250201','2025-02-01')");

            /* Ministries */
            $pdo->exec("INSERT INTO MINISTRY (name,category,icon,color,leader,schedule,desc_short) VALUES
                ('Worship Ministry',   'worship',    'ti-music',       '#3D0C2F','David Khumalo', 'Sundays 8:00 AM - Wednesdays 6:00 PM','Choir, live band, and audio/visual team.'),
                ('Youth Ministry',     'youth',      'ti-users',       '#639922','Elder Nkosi',   'Fridays 6:00 PM - Youth Centre',      'Empowering ages 13-25 through weekly nights.'),
                ('Women of Purpose',   'women',      'ti-heart',       '#C9A84C','Grace Mokoena', 'Last Saturday of every month 10:00 AM','Monthly gatherings for women of all ages.'),
                ('Community Outreach', 'outreach',   'ti-world',       '#378ADD','Samuel Osei',   'First Saturday of every month 8:00 AM','Monthly food drives and neighbourhood visits.'),
                (\"Children's Ministry\",'children',  'ti-school',      '#C0392B','Zanele Dlamini','Sundays 9:00 AM and 11:00 AM',        'Fun, safe, Bible-based programmes for ages 4-12.'),
                ('Cell Groups',        'community',  'ti-home-2',      '#5C1A47','Various Leaders','Various days - Pretoria suburbs',     '12 active cell groups meeting weekly.'),
                ('Hospitality & Ushers','hospitality','ti-hand-finger', '#0F6E56','Nomsa Sithole', 'Sundays 8:30 AM - Main Entrance',     'Welcoming every visitor with warmth.'),
                ('Prayer Ministry',    'prayer',     'ti-hand-stop',   '#533AB7','Pastor Sithole','Wednesdays 5:30 AM - Sundays after service','Intercession, prayer chains, and ministry.')");

            /* Volunteer roles */
            $pdo->exec("INSERT INTO VOLUNTEER (member_id,ministry_name,role_name,status,start_date,hours) VALUES
                (2,'Worship',    'Worship Team Vocalist','active',    '2024-01-01',85.0),
                (2,'Cell Groups','Cell Group Leader',    'active',    '2022-03-01',35.5),
                (2,'Hospitality','Usher',                'interested', NULL,        0.0)");

            /* Announcements */
            $pdo->exec("INSERT INTO ANNOUNCEMENT (title,body,icon,color,created_at) VALUES
                ('Youth Camp 2025 - Last 10 spots!',
                 'Hartbeespoort Resort. Transport departs at 4:00 PM from the church.',
                 'ti-calendar-event','blue', NOW() - INTERVAL 2 DAY),
                ('Building Fund - 62% reached',
                 'Thank you for your generous giving. On track to break ground in September 2025.',
                 'ti-heart','green', NOW() - INTERVAL 4 DAY),
                ('New Cell Group launching in Centurion',
                 'A new Thursday evening group is starting. Contact Pastor Dlamini to join.',
                 'ti-message','gold', NOW() - INTERVAL 7 DAY),
                ('Sunday Service time change - 21 July',
                 'The 9:00 AM service will begin at 8:30 AM on 21 July due to a special guest preacher.',
                 'ti-info-circle','navy', NOW() - INTERVAL 7 DAY)");

            $success[] = "✅ All sample data inserted.";
        } else {
            $success[] = "ℹ️ Sample data skipped — database already has {$existing} member(s).";
        }

        $success[] = "🎉 <strong>Installation complete!</strong> You can now log in.";

    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Shammah Urban Church</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#f4f1ee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .card{background:#fff;border-radius:14px;box-shadow:0 6px 32px rgba(0,0,0,.13);max-width:580px;width:100%;overflow:hidden}
  .head{background:#3D0C2F;color:#fff;padding:2rem 2.5rem;display:flex;align-items:center;gap:1rem}
  .head-logo{font-size:2rem;font-weight:900;background:rgba(255,255,255,.15);border-radius:10px;width:52px;height:52px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .head h1{font-size:1.25rem;margin-bottom:.2rem}
  .head p{opacity:.75;font-size:.88rem}
  .body{padding:2rem 2.5rem}
  .info-box{background:#f0edf4;border-left:4px solid #3D0C2F;padding:.9rem 1.1rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem;font-size:.9rem;color:#444;line-height:1.7}
  .field{margin-bottom:1.2rem}
  label{display:block;font-size:.85rem;font-weight:600;color:#555;margin-bottom:.4rem}
  input[type=password],input[type=text]{width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;transition:border .2s}
  input:focus{border-color:#3D0C2F}
  .hint{font-size:.8rem;color:#888;margin-top:.3rem}
  .btn{width:100%;padding:.9rem;background:#3D0C2F;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s;margin-top:.5rem}
  .btn:hover{background:#5a1245}
  .messages{margin-bottom:1.5rem}
  .msg{padding:.65rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:.5rem;line-height:1.6}
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

    <?php if (!empty($success) || !empty($errors)): ?>
      <div class="messages">
        <?php foreach ($success as $msg): ?>
          <div class="msg <?= str_contains($msg,'complete') ? 'final' : 'ok' ?>"><?= $msg ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $msg): ?>
          <div class="msg err">❌ <?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if (empty($errors)): ?>
        <div class="creds">
          <h3>Login Credentials</h3>
          <table>
            <tr><td>Admin email</td><td><code>admin@shammah.co.za</code></td></tr>
            <tr><td>Admin password</td><td><code>Admin@2025</code></td></tr>
            <tr><td>Member email</td><td><code>thabo@email.com</code></td></tr>
            <tr><td>Member password</td><td><code>Member@2025</code></td></tr>
          </table>
        </div>
        <a href="login.php" class="login-btn">→ Go to Login Page</a>
        <div class="delete-warn">
          ⚠️ <strong>Security:</strong> Delete <code>install.php</code> from your project folder after logging in.
        </div>
      <?php endif; ?>

    <?php else: ?>

      <div class="info-box">
        This will automatically:<br>
        • Create the <strong>shammah_db</strong> database<br>
        • Create all 10 tables<br>
        • Insert sample data and login accounts<br><br>
        Make sure <strong>Apache + MySQL are running</strong> in XAMPP first.
      </div>

      <form method="POST">
        <div class="field">
          <label for="db_pass">MySQL Password</label>
          <input type="password" id="db_pass" name="db_pass"
                 placeholder="Leave blank if XAMPP default (no password)">
          <div class="hint">Most XAMPP installations have no password — just leave this empty.</div>
        </div>
        <button class="btn" type="submit">⚡ Install Database Now</button>
      </form>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
