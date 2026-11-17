-- ═══════════════════════════════════════════════════════════
--  db_update_shared_login.sql  —  Shared login for website + Android app
--
--  Import this AFTER db_setup.sql (phpMyAdmin > shammah_db > Import).
--  It is safe to import more than once.
--  (The site also runs these same steps automatically the first time
--   someone logs in, so forgetting this file will not break anything.)
--
--  What it adds:
--   • MEMBER.failed_logins / MEMBER.locked_until
--       lockout after 3 wrong passwords, shared by the app and the website
--   • OTP_CODE   – 6-digit codes sent by email + SMS (expire after 10 min)
--   • API_TOKEN  – sign-in tokens for the Android app (30 days)
--   • Phone numbers are rewritten to +27 format so you can log in with them
-- ═══════════════════════════════════════════════════════════

USE shammah_db;

ALTER TABLE MEMBER
    ADD COLUMN IF NOT EXISTS failed_logins INT      NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS locked_until  DATETIME NULL;

CREATE TABLE IF NOT EXISTS OTP_CODE (
    otp_id      INT          NOT NULL AUTO_INCREMENT,
    member_id   INT          NOT NULL,
    purpose     VARCHAR(20)  NOT NULL,              -- login | register | reset
    ticket_hash CHAR(64)     NOT NULL,              -- sha256 of the ticket given to the browser/app
    code_hash   VARCHAR(255) NOT NULL,              -- bcrypt of the 6-digit code
    attempts    INT          NOT NULL DEFAULT 0,
    sends       INT          NOT NULL DEFAULT 1,
    last_sent   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (otp_id),
    UNIQUE KEY uq_ticket (ticket_hash),
    INDEX idx_member (member_id, created_at),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS API_TOKEN (
    token_id     INT         NOT NULL AUTO_INCREMENT,
    member_id    INT         NOT NULL,
    token_hash   CHAR(64)    NOT NULL,              -- sha256 of the token kept on the phone
    device       VARCHAR(80) NOT NULL DEFAULT 'Android app',
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME    NULL,
    expires_at   DATETIME    NOT NULL,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_token (token_hash),
    FOREIGN KEY (member_id) REFERENCES MEMBER(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A new person waits here until they type the correct 6-digit code.
-- Only then is a row created in MEMBER. Unverified details are deleted automatically.
CREATE TABLE IF NOT EXISTS PENDING_REGISTRATION (
    pending_id  INT          NOT NULL AUTO_INCREMENT,
    ticket_hash CHAR(64)     NOT NULL,
    email       VARCHAR(120) NOT NULL,
    phone       VARCHAR(30)  NOT NULL DEFAULT '',
    details     TEXT         NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    attempts    INT          NOT NULL DEFAULT 0,
    sends       INT          NOT NULL DEFAULT 1,
    last_sent   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pending_id),
    UNIQUE KEY uq_pending_ticket (ticket_hash),
    INDEX idx_pending_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 082 345 6789  ->  +27823456789   (South African numbers)
UPDATE MEMBER
SET    phone = CONCAT('+27', SUBSTRING(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), 2))
WHERE  REPLACE(REPLACE(phone, ' ', ''), '-', '') REGEXP '^0[0-9]{9}$';