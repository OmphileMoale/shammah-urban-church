-- ═══════════════════════════════════════════════════════════
--  db_setup.sql  —  Shammah Urban Church Database
--
--  HOW TO USE:
--  1. Open XAMPP → phpMyAdmin (http://localhost/phpmyadmin)
--  2. Click "New" in the left sidebar
--  3. Name the database:  shammah_db   → click Create
--  4. Click the "Import" tab
--  5. Choose this file → click Go
--
--  This script creates all tables and inserts sample data
--  so the portal works immediately after import.
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS shammah_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shammah_db;

-- ─────────────────────────────────────────────────────────
--  TABLE: MEMBER
--  One row per registered user.
--  password_hash stores bcrypt (never plain text).
--  role controls portal access level.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS MEMBER (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: ADDRESS
--  Separate from MEMBER so one member can
--  have multiple addresses in future.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ADDRESS (
    address_id  INT          NOT NULL AUTO_INCREMENT,
    member_id   INT          NOT NULL,
    street      VARCHAR(120) NOT NULL DEFAULT '',
    city        VARCHAR(60)  NOT NULL DEFAULT '',
    postal_code VARCHAR(10)  NOT NULL DEFAULT '',
    PRIMARY KEY (address_id),
    UNIQUE KEY uq_member (member_id),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: EVENT
--  Church events. RSVP links to this table.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS EVENT (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: RSVP
--  One row per member per event.
--  ON DUPLICATE KEY UPDATE allows re-confirming.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS RSVP (
    rsvp_id    INT         NOT NULL AUTO_INCREMENT,
    member_id  INT         NOT NULL,
    event_id   INT         NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rsvp_id),
    UNIQUE KEY uq_member_event (member_id, event_id),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)  REFERENCES EVENT(event_id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: ATTENDANCE
--  Marked after an event has happened.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ATTENDANCE (
    attendance_id INT         NOT NULL AUTO_INCREMENT,
    member_id     INT         NOT NULL,
    event_id      INT         NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'present',
    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_att (member_id, event_id),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)  REFERENCES EVENT(event_id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: DONATION
--  Financial giving records per member.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS DONATION (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: MINISTRY
--  The 8 active church ministries.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS MINISTRY (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: MINISTRY_MEMBER
--  Junction table — which members joined which ministry.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS MINISTRY_MEMBER (
    id          INT      NOT NULL AUTO_INCREMENT,
    member_id   INT      NOT NULL,
    ministry_id INT      NOT NULL,
    date_joined DATE     NOT NULL DEFAULT (CURDATE()),
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mm (member_id, ministry_id),
    FOREIGN KEY (member_id)   REFERENCES MEMBER(member_id)   ON DELETE CASCADE,
    FOREIGN KEY (ministry_id) REFERENCES MINISTRY(ministry_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: VOLUNTEER
--  Volunteer role assignments per member.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS VOLUNTEER (
    volunteer_id  INT         NOT NULL AUTO_INCREMENT,
    member_id     INT         NOT NULL,
    ministry_name VARCHAR(80) NOT NULL,
    role_name     VARCHAR(80) NOT NULL DEFAULT '',
    status        VARCHAR(20) NOT NULL DEFAULT 'interested',
    start_date    DATE        NULL,
    hours         DECIMAL(6,1) NOT NULL DEFAULT 0,
    PRIMARY KEY (volunteer_id),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
--  TABLE: ANNOUNCEMENT
--  Church notices shown in the member portal.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ANNOUNCEMENT (
    announcement_id INT          NOT NULL AUTO_INCREMENT,
    title           VARCHAR(120) NOT NULL,
    body            TEXT         NOT NULL,
    icon            VARCHAR(30)  NOT NULL DEFAULT 'ti-info-circle',
    color           VARCHAR(20)  NOT NULL DEFAULT 'navy',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (announcement_id),
    INDEX idx_active (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ═══════════════════════════════════════════════════════════
--  SEED DATA  —  Sample records so the portal works on day 1
-- ═══════════════════════════════════════════════════════════

-- ── DEMO MEMBERS ──────────────────────────────────────────
-- Passwords below hash to:
--   admin@shammah.co.za   →  Admin@2025
--   thabo@email.com       →  Member@2025
-- Generated with: password_hash('Admin@2025', PASSWORD_BCRYPT)
-- IMPORTANT: Change these passwords after first login!

INSERT INTO MEMBER (title, first_name, last_name, email, phone, date_of_birth, gender, membership_type, password_hash, role, is_active, created_at)
VALUES
(
    'Mr', 'Admin', 'Shammah',
    'admin@shammah.co.za',
    '012 345 6789',
    '1985-01-01',
    'Male',
    'Full Member',
    '$2y$12$prXP3CpPlnNHDAhy1xCdTuzE7ziBjpApXpGtKUpHUY6WoejdJyqKy', -- Admin@2025
    'admin',
    1,
    '2021-01-01 08:00:00'
),
(
    'Mr', 'Thabo', 'Nkosi',
    'thabo@email.com',
    '071 234 5678',
    '1995-03-15',
    'Male',
    'Full Member',
    '$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW', -- Member@2025
    'member',
    1,
    '2021-03-15 10:00:00'
);

-- ── ADDRESSES ─────────────────────────────────────────────
INSERT INTO ADDRESS (member_id, street, city, postal_code)
VALUES
(1, '1103 Eland Street, Mooikloof', 'Pretoria East', '0059'),
(2, '1103 Eland Street, Mooikloof', 'Pretoria East', '0059');

-- ── EVENTS ────────────────────────────────────────────────
INSERT INTO EVENT (title, event_date, day_str, month_str, time_str, venue, capacity, color, category, description)
VALUES
('Sunday Morning Service',    '2025-06-15 09:00:00', '15', 'Jun', '9:00 AM',         'Main Sanctuary',      500, '#3D0C2F', 'Worship',  'Join us for our Sunday morning worship service.'),
('Youth Camp 2025',           '2025-06-20 16:00:00', '20', 'Jun', 'Fri–Sun Weekend', 'Hartbeespoort Resort', 120, '#639922', 'Youth',   'An unforgettable weekend for young people aged 13–25.'),
('Community Prayer Night',    '2025-06-18 19:00:00', '18', 'Jun', '7:00 PM',         'Fellowship Hall',     150, '#378ADD', 'Outreach', 'A powerful evening of corporate intercession.'),
('Women of Purpose Meeting',  '2025-06-25 10:00:00', '25', 'Jun', '10:00 AM',        'Conference Room B',   100, '#C9A84C', 'Women',   'Our monthly Women of Purpose gathering.'),
("Children's Ministry Fun Day",'2025-06-28 09:00:00','28', 'Jun', '9:00 AM – 1:00 PM','Church Grounds',      80, '#C0392B', 'Children', 'A special Saturday morning for children aged 4–12.');

-- ── SAMPLE RSVPs for demo member (Thabo) ──────────────────
INSERT INTO RSVP (member_id, event_id, status)
VALUES (2, 2, 'confirmed'), (2, 3, 'confirmed');

-- ── SAMPLE ATTENDANCE for demo member ─────────────────────
-- Past event IDs reuse the same EVENT table for simplicity
INSERT INTO ATTENDANCE (member_id, event_id, status)
VALUES
(2, 1, 'present'),
(2, 3, 'present');

-- ── SAMPLE DONATIONS for demo member ──────────────────────
INSERT INTO DONATION (member_id, amount, donation_type, payment_method, reference, donation_date)
VALUES
(2, 2500.00, 'Tithe',         'EFT',  'TH-20250501', '2025-05-01'),
(2, 2500.00, 'Tithe',         'EFT',  'TH-20250401', '2025-04-01'),
(2, 1000.00, 'Building Fund', 'Card', 'BF-20250315', '2025-03-15'),
(2, 2500.00, 'Tithe',         'EFT',  'TH-20250301', '2025-03-01'),
(2,  500.00, 'Offering',      'Cash', 'OF-20250210', '2025-02-10'),
(2, 2500.00, 'Tithe',         'EFT',  'TH-20250201', '2025-02-01');

-- ── MINISTRIES ────────────────────────────────────────────
INSERT INTO MINISTRY (name, category, icon, color, leader, schedule, desc_short)
VALUES
('Worship Ministry',    'worship',     'ti-music',        '#3D0C2F', 'David Khumalo',   'Sundays 8:00 AM · Wednesdays 6:00 PM',           'Choir, live band, and audio/visual team.'),
('Youth Ministry',      'youth',       'ti-users',        '#639922', 'Elder Nkosi',     'Fridays 6:00 PM · Youth Centre',                  'Empowering ages 13–25 through weekly nights.'),
('Women of Purpose',    'women',       'ti-heart',        '#C9A84C', 'Grace Mokoena',   'Last Saturday of every month · 10:00 AM',         'Monthly gatherings for women of all ages.'),
('Community Outreach',  'outreach',    'ti-world',        '#378ADD', 'Samuel Osei',     'First Saturday of every month · 8:00 AM',         'Monthly food drives and neighbourhood visits.'),
("Children's Ministry", 'children',   'ti-school',       '#C0392B', 'Zanele Dlamini',  'Sundays 9:00 AM & 11:00 AM',                      'Fun, safe, Bible-based programmes for ages 4–12.'),
('Cell Groups',         'community',  'ti-home-2',       '#5C1A47', 'Various Leaders', 'Various days · Pretoria suburbs',                  '12 active cell groups meeting weekly.'),
('Hospitality & Ushers','hospitality','ti-hand-finger',  '#0F6E56', 'Nomsa Sithole',   'Sundays 8:30 AM · Main Entrance',                 'Welcoming every visitor with warmth.'),
('Prayer Ministry',     'prayer',     'ti-hand-stop',    '#533AB7', 'Pastor Sithole',  'Wednesdays 5:30 AM · Sundays after service',      'Intercession, prayer chains, and ministry.');

-- ── VOLUNTEER ROLES for demo member ───────────────────────
INSERT INTO VOLUNTEER (member_id, ministry_name, role_name, status, start_date, hours)
VALUES
(2, 'Worship',     'Worship Team Vocalist', 'active',     '2024-01-01', 85.0),
(2, 'Cell Groups', 'Cell Group Leader',     'active',     '2022-03-01', 35.5),
(2, 'Hospitality', 'Usher',                 'interested', NULL,          0.0);

-- ── ANNOUNCEMENTS ─────────────────────────────────────────
INSERT INTO ANNOUNCEMENT (title, body, icon, color, created_at)
VALUES
('Youth Camp 2025 — Last 10 spots!',
 'Hartbeespoort Resort, 20 Jun. Transport departs at 4:00 PM from the church.',
 'ti-calendar-event', 'blue', NOW() - INTERVAL 2 DAY),

('Building Fund — 62% reached',
 'Thank you for your generous giving. We are on track to break ground in September 2025.',
 'ti-heart', 'green', NOW() - INTERVAL 4 DAY),

('New Cell Group launching in Centurion',
 'A new Thursday evening group is starting. Contact Pastor Dlamini to join.',
 'ti-message', 'gold', NOW() - INTERVAL 7 DAY),

('Sunday Service time change — 21 July',
 'The 9:00 AM service will begin at 8:30 AM on 21 July due to a special guest preacher.',
 'ti-info-circle', 'navy', NOW() - INTERVAL 7 DAY);


-- ═══════════════════════════════════════════════════════════
--  TASK 2 ADDITIONS
--  New tables + extra seed data so EVERY table has 10+ records
--  Demo logins (website):
--    admin@shammah.co.za   / Admin@2025   (admin)
--    pastor@shammah.co.za  / Admin@2025   (pastor)
--    thabo@email.com       / Member@2025  (member)
--    all other seeded members use Member@2025
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS SERMON (
    sermon_id    INT          NOT NULL AUTO_INCREMENT,
    title        VARCHAR(120) NOT NULL,
    speaker      VARCHAR(80)  NOT NULL,
    series       VARCHAR(80)  NOT NULL DEFAULT '',
    preached_on  DATE         NOT NULL,
    duration_min INT          NOT NULL DEFAULT 45,
    video_url    VARCHAR(255) NOT NULL DEFAULT '',
    audio_url    VARCHAR(255) NOT NULL DEFAULT '',
    notes_url    VARCHAR(255) NOT NULL DEFAULT '',
    description  VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (sermon_id),
    INDEX idx_preached (preached_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS PRAYER_REQUEST (
    request_id   INT          NOT NULL AUTO_INCREMENT,
    member_id    INT          NULL,
    display_name VARCHAR(80)  NOT NULL DEFAULT 'Anonymous',
    category     VARCHAR(40)  NOT NULL DEFAULT 'General',
    message      TEXT         NOT NULL,
    is_public    TINYINT(1)   NOT NULL DEFAULT 1,
    status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
    prayer_count INT          NOT NULL DEFAULT 0,
    is_answered  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by  INT          NULL,
    reviewed_at  DATETIME     NULL,
    PRIMARY KEY (request_id),
    INDEX idx_status (status),
    FOREIGN KEY (member_id)   REFERENCES MEMBER(member_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES MEMBER(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS GALLERY (
    photo_id    INT          NOT NULL AUTO_INCREMENT,
    filename    VARCHAR(120) NOT NULL,
    category    VARCHAR(40)  NOT NULL DEFAULT 'General',
    caption     VARCHAR(160) NOT NULL DEFAULT '',
    uploaded_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id),
    INDEX idx_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS CONTACT_MESSAGE (
    message_id INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)  NOT NULL,
    email      VARCHAR(120) NOT NULL,
    subject    VARCHAR(80)  NOT NULL DEFAULT 'General Enquiry',
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MORE MEMBERS (ids 3 to 12) ────────────────────────────
INSERT INTO MEMBER (title, first_name, last_name, email, phone, date_of_birth, gender, membership_type, password_hash, role, is_active, created_at)
VALUES
('Pastor','Sipho','Sithole','pastor@shammah.co.za','012 345 1111','1978-04-02','Male','Full Member','$2y$12$prXP3CpPlnNHDAhy1xCdTuzE7ziBjpApXpGtKUpHUY6WoejdJyqKy','pastor',1,'2021-01-02 08:00:00'),
('Ms','Zanele','Dlamini','zanele.d@gmail.com','082 345 6789','1999-05-21','Female','Youth','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2022-02-10 10:00:00'),
('Mr','Samuel','Osei','s.osei@outlook.com','063 456 7890','1988-09-12','Male','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2022-03-11 10:00:00'),
('Mrs','Grace','Mokoena','grace.m@email.com','071 111 2222','1980-11-24','Female','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2021-06-01 10:00:00'),
('Mr','David','Khumalo','david.k@email.com','072 222 3333','1990-08-22','Male','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2021-07-12 10:00:00'),
('Ms','Nomsa','Sithole','nomsa.s@email.com','073 333 4444','1993-01-30','Female','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2023-01-15 10:00:00'),
('Mr','Sipho','Mahlangu','sipho.m@email.com','074 444 5555','1985-06-18','Male','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2023-04-02 10:00:00'),
('Mrs','Ruth','Molefe','ruth.m@email.com','075 555 6666','1975-12-05','Female','Full Member','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2020-09-20 10:00:00'),
('Mr','Kabelo','Phiri','kabelo.p@email.com','076 666 7777','2001-03-09','Male','Guest','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',1,'2026-09-11 10:00:00'),
('Ms','Aisha','Patel','aisha.p@email.com','077 777 8888','1996-07-14','Female','Guest','$2y$12$XNBpwN7nyr5RYV7YUu1H/eHCWx0kR0FH1ZU.6ACTn0QL22AetR1GW','member',0,'2024-05-05 10:00:00');

INSERT INTO ADDRESS (member_id, street, city, postal_code)
VALUES
(3,'14 Jacaranda Street, Sunnyside','Pretoria','0002'),
(4,'22 Church Street, Arcadia','Pretoria','0083'),
(5,'5 Protea Road, Hatfield','Pretoria','0028'),
(6,'18 Rooibos Avenue, Moreleta Park','Pretoria','0044'),
(7,'9 Acacia Lane, Brooklyn','Pretoria','0181'),
(8,'31 Baobab Drive, Waterkloof','Pretoria','0145'),
(9,'7 Marula Street, Mooikloof','Pretoria East','0059'),
(10,'12 Aloe Close, Garsfontein','Pretoria East','0042'),
(11,'3 Fynbos Way, Lynnwood','Pretoria','0081'),
(12,'44 Karee Road, Centurion','Centurion','0157');

-- ── MORE EVENTS (upcoming, 2026) ──────────────────────────
INSERT INTO EVENT (title, event_date, day_str, month_str, time_str, venue, capacity, color, category, description)
VALUES
('Sunday Celebration Service','2026-10-25 09:00:00','25','Oct','9:00 AM','Main Sanctuary',500,'#3D0C2F','Worship','Weekly celebration service.'),
('Community Outreach Day','2026-10-31 08:00:00','31','Oct','8:00 AM','Sunnyside Park',200,'#378ADD','Outreach','Food drive and neighbourhood visits.'),
('Women of Purpose Meeting','2026-11-07 10:00:00','07','Nov','10:00 AM','Conference Room B',100,'#C9A84C','Women','Monthly Women of Purpose gathering.'),
('Youth Camp 2026','2026-11-20 16:00:00','20','Nov','Fri-Sun Weekend','Hartbeespoort Resort',120,'#639922','Youth','Weekend of worship, games and teaching.'),
('Year-End Thanksgiving Service','2026-12-13 09:00:00','13','Dec','9:00 AM','Main Sanctuary',500,'#5C1A47','Worship','Thanksgiving and testimonies.');

-- ── RSVP and ATTENDANCE (members 3-10 -> event 1) ─────────
INSERT INTO RSVP (member_id, event_id, status)
VALUES (3,1,'confirmed'),(4,1,'confirmed'),(5,1,'confirmed'),(6,1,'confirmed'),(7,1,'confirmed'),(8,1,'confirmed'),(9,1,'confirmed'),(10,1,'confirmed');

INSERT INTO ATTENDANCE (member_id, event_id, status)
VALUES (3,1,'present'),(4,1,'present'),(5,1,'present'),(6,1,'present'),(7,1,'present'),(8,1,'present'),(9,1,'absent'),(10,1,'present');

-- ── MORE DONATIONS ────────────────────────────────────────
INSERT INTO DONATION (member_id, amount, donation_type, payment_method, reference, donation_date)
VALUES
(4,  500.00,'Offering',     'Cash','OF-20260906','2026-09-06'),
(5, 1200.00,'Tithe',        'EFT', 'TH-20260901','2026-09-01'),
(6, 3000.00,'Building Fund','Card','BF-20260915','2026-09-15'),
(7,  800.00,'Tithe',        'EFT', 'TH-20260901','2026-09-01'),
(8, 1500.00,'Special Seed', 'Card','SS-20260920','2026-09-20'),
(9, 2000.00,'Tithe',        'EFT', 'TH-20261001','2026-10-01'),
(10, 650.00,'Offering',     'Cash','OF-20261004','2026-10-04'),
(11, 400.00,'Offering',     'Cash','OF-20261011','2026-10-11');

-- ── MORE MINISTRIES (now 10) ──────────────────────────────
INSERT INTO MINISTRY (name, category, icon, color, leader, schedule, desc_short)
VALUES
('Mens Ministry','men','ti-users','#1F4E79','Sipho Mahlangu','Monthly breakfast · 1st Saturday','Mentorship and accountability for men.'),
('Media and Technical','technical','ti-device-laptop','#444444','Sound Team Lead','Sundays 7:30 AM','Sound, cameras and live stream.');

-- ── MINISTRY MEMBERS ──────────────────────────────────────
INSERT INTO MINISTRY_MEMBER (member_id, ministry_id, date_joined)
VALUES
(2,1,'2024-01-01'),(3,2,'2022-02-15'),(4,4,'2022-03-20'),(5,3,'2021-06-10'),(6,1,'2021-07-20'),
(7,7,'2023-01-20'),(8,9,'2023-04-10'),(9,8,'2020-10-01'),(10,2,'2026-09-15'),(11,10,'2024-05-20');

-- ── MORE VOLUNTEERS ───────────────────────────────────────
INSERT INTO VOLUNTEER (member_id, ministry_name, role_name, status, start_date, hours)
VALUES
(3,'Youth','Youth Mentor','active','2022-03-01',40.0),
(4,'Outreach','Food Drive Helper','active','2022-04-01',28.5),
(5,'Women','Event Coordinator','active','2021-07-01',60.0),
(6,'Worship','Drummer','active','2021-08-01',72.0),
(7,'Hospitality','Usher','active','2023-02-01',30.0),
(8,'Technical','Sound Engineer','active','2023-05-01',45.0),
(9,'Prayer','Intercessor','active','2020-10-01',90.0),
(10,'Children','Sunday School Helper','interested',NULL,0.0);

-- ── MORE ANNOUNCEMENTS ────────────────────────────────────
INSERT INTO ANNOUNCEMENT (title, body, icon, color, created_at)
VALUES
('Youth Camp 2026 registration open','Register by 10 November. Limited spots.','ti-calendar-event','blue','2026-10-10 09:00:00'),
('Year-End Thanksgiving Service','Join us on 13 December at 9:00 AM.','ti-heart','green','2026-10-12 09:00:00'),
('New App Available','The Shammah Android app can now be installed from the website.','ti-device-mobile','gold','2026-10-14 09:00:00'),
('Community Outreach Day','Volunteers needed on 31 October at Sunnyside Park.','ti-world','navy','2026-10-15 09:00:00'),
('Cell Groups Restart','All cell groups restart in the first week of November.','ti-home-2','navy','2026-10-16 09:00:00'),
('Prayer Night','Community prayer night every Wednesday at 6:30 PM.','ti-hand-stop','gold','2026-10-17 09:00:00');

-- ── SERMONS (10) ──────────────────────────────────────────
INSERT INTO SERMON (title, speaker, series, preached_on, duration_min, video_url, audio_url, notes_url, description)
VALUES
('Walking in Faith','Pastor Sithole','Foundations of Faith','2026-10-18',52,'https://www.youtube.com/@shammahurbanchurch','','','Trusting God through uncertainty, from Hebrews 11.'),
('Gods Grace Renewed','Pastor Sithole','Foundations of Faith','2026-10-11',48,'https://www.youtube.com/@shammahurbanchurch','','','New mercies every morning.'),
('Praying Without Ceasing','Elder Nkosi','Prayer Life','2026-10-04',35,'https://www.youtube.com/@shammahurbanchurch','','','Building a daily prayer habit.'),
('The Power of Community','Pastor Sithole','Church Family','2026-09-27',55,'https://www.youtube.com/@shammahurbanchurch','','','Why we need one another.'),
('Hope in the Desert','Grace Mokoena','Women of Purpose','2026-09-20',42,'https://www.youtube.com/@shammahurbanchurch','','','Finding hope in dry seasons.'),
('Rooted and Rising','Guest Speaker','Foundations of Faith','2026-09-13',44,'https://www.youtube.com/@shammahurbanchurch','','','Growing deep before growing tall.'),
('Serving with Joy','Elder Nkosi','Church Family','2026-09-06',38,'https://www.youtube.com/@shammahurbanchurch','','','The heart of service.'),
('Generous Living','Pastor Sithole','Stewardship','2026-08-30',50,'https://www.youtube.com/@shammahurbanchurch','','','Giving as worship.'),
('Peace in the Storm','Grace Mokoena','Women of Purpose','2026-08-23',40,'https://www.youtube.com/@shammahurbanchurch','','','Jesus calms the storm.'),
('Youth on Fire','Elder Nkosi','Next Generation','2026-08-16',36,'https://www.youtube.com/@shammahurbanchurch','','','Encouraging young believers.');

-- ── PRAYER REQUESTS (10: mix of pending / approved / rejected) ─
INSERT INTO PRAYER_REQUEST (member_id, display_name, category, message, is_public, status, prayer_count, is_answered, created_at)
VALUES
(NULL,'Anonymous','Health','Please pray for my mothers recovery from surgery.',1,'approved',14,0,'2026-10-17 07:00:00'),
(4,'Zanele D.','Thanksgiving','God provided my family with a new home. Thank you Shammah family!',1,'approved',32,1,'2026-10-16 09:00:00'),
(2,'Thabo N.','Spiritual Growth','Pray for consistency in my morning devotions.',1,'approved',8,0,'2026-10-15 09:00:00'),
(5,'Samuel O.','Work','Please pray for wisdom in a job interview this week.',1,'approved',11,0,'2026-10-14 09:00:00'),
(6,'Grace M.','Family','Pray for peace in our family during the holidays.',1,'approved',19,0,'2026-10-13 09:00:00'),
(7,'David K.','Finances','Pray for provision as I start a new business.',1,'pending',0,0,'2026-10-18 08:00:00'),
(8,'Nomsa S.','Health','Please pray for healing for my sister.',1,'pending',0,0,'2026-10-18 09:00:00'),
(NULL,'Anonymous','Spiritual Growth','Pray for strength in a hard season.',1,'pending',0,0,'2026-10-18 10:00:00'),
(9,'Sipho M.','Family','Private request for the prayer team.',0,'approved',0,0,'2026-10-12 09:00:00'),
(NULL,'Anonymous','General','Spam test message that was rejected.',1,'rejected',0,0,'2026-10-11 09:00:00');

-- ── GALLERY (10) ──────────────────────────────────────────
INSERT INTO GALLERY (filename, category, caption, uploaded_at)
VALUES
('church-building.svg','Sunday Services','Our church building in Sunnyside','2026-09-01 10:00:00'),
('sunday-1.jpg','Sunday Services','Sunday worship','2026-09-02 10:00:00'),
('sunday-2.jpg','Sunday Services','Congregation singing','2026-09-09 10:00:00'),
('youth-1.jpg','Youth Events','Youth camp 2026 games','2026-09-16 10:00:00'),
('youth-2.jpg','Youth Events','Youth worship night','2026-09-23 10:00:00'),
('outreach-1.jpg','Outreach','Food drive in Sunnyside','2026-09-30 10:00:00'),
('outreach-2.jpg','Outreach','School supply collection','2026-10-02 10:00:00'),
('worship-1.jpg','Worship','Worship team on stage','2026-10-05 10:00:00'),
('worship-2.jpg','Worship','Choir rehearsal','2026-10-07 10:00:00'),
('women-1.jpg','Women of Purpose','Monthly gathering','2026-10-09 10:00:00');

-- ── CONTACT MESSAGES (10) ─────────────────────────────────
INSERT INTO CONTACT_MESSAGE (name, email, subject, message, is_read, created_at)
VALUES
('Thabo Nkosi','thabo@email.com','General Enquiry','What time does the second service start?',1,'2026-10-01 09:00:00'),
('Lerato Mokone','lerato@email.com','Volunteering','I would like to join the ushers team.',1,'2026-10-02 09:00:00'),
('Peter Dube','peter@email.com','Prayer','Please keep my family in prayer.',1,'2026-10-03 09:00:00'),
('Naledi Tau','naledi@email.com','Events','Is there parking at Youth Camp?',1,'2026-10-04 09:00:00'),
('Johan Venter','johan@email.com','General Enquiry','Do you offer a childrens programme?',0,'2026-10-06 09:00:00'),
('Mpho Khoza','mpho@email.com','Giving','How do I get a donation receipt?',0,'2026-10-08 09:00:00'),
('Aisha Patel','aisha.p@email.com','Membership','How do I become a member?',0,'2026-10-10 09:00:00'),
('Sam Naidoo','sam@email.com','Events','Can I bring friends to the outreach day?',0,'2026-10-12 09:00:00'),
('Busi Zulu','busi@email.com','Volunteering','Do you need sound engineers?',0,'2026-10-14 09:00:00'),
('Kagiso Mathe','kagiso@email.com','General Enquiry','Where can I download the mobile app?',0,'2026-10-16 09:00:00');
