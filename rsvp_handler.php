<?php
/* ═══════════════════════════════════════
   rsvp_handler.php  —  RSVP ENDPOINT
   
   Handles two actions:
   POST  action=rsvp    → confirm an RSVP
   POST  action=cancel  → cancel an RSVP
   
   Returns JSON {ok, msg}
═══════════════════════════════════════ */
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

/* ── AUTH GUARD ──────────────────────── */
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'You must be logged in.']);
    exit;
}

/* ── CSRF CHECK ──────────────────────── */
$csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
           && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
if (!$csrf_ok) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Refresh and try again.']);
    exit;
}

$member_id = (int) $_SESSION['member_id'];
$event_id  = (int) ($_POST['event_id'] ?? 0);
$action    = $_POST['action'] ?? 'rsvp';

if ($event_id < 1) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid event.']);
    exit;
}

try {
    if ($action === 'cancel') {
        /* ── CANCEL RSVP ─────────────────
           Set status to "cancelled" rather
           than deleting — keeps audit trail.
        ──────────────────────────────────── */
        $stmt = $pdo->prepare('
            UPDATE RSVP SET status = "cancelled"
            WHERE  member_id = ? AND event_id = ?
        ');
        $stmt->execute([$member_id, $event_id]);

        echo json_encode(['ok' => true, 'msg' => 'Your RSVP has been cancelled.']);

    } else {
        /* ── CONFIRM RSVP ────────────────
           Check capacity before inserting.
           ON DUPLICATE KEY UPDATE re-confirms
           a previously cancelled RSVP.
        ──────────────────────────────────── */

        /* Check how full the event is */
        $cap = $pdo->prepare('
            SELECT e.capacity,
                   COUNT(r.rsvp_id) AS rsvp_count
            FROM   EVENT e
            LEFT   JOIN RSVP r
                   ON r.event_id = e.event_id AND r.status = "confirmed"
            WHERE  e.event_id = ?
            GROUP  BY e.event_id
        ');
        $cap->execute([$event_id]);
        $ev = $cap->fetch();

        if (!$ev) {
            echo json_encode(['ok' => false, 'msg' => 'Event not found.']);
            exit;
        }

        if ($ev['rsvp_count'] >= $ev['capacity']) {
            echo json_encode(['ok' => false, 'msg' => 'Sorry, this event is fully booked.']);
            exit;
        }

        /* Insert or re-confirm */
        $stmt = $pdo->prepare('
            INSERT INTO RSVP (member_id, event_id, status, created_at)
            VALUES (?, ?, "confirmed", NOW())
            ON DUPLICATE KEY UPDATE status = "confirmed"
        ');
        $stmt->execute([$member_id, $event_id]);

        echo json_encode(['ok' => true, 'msg' => 'RSVP confirmed! We look forward to seeing you.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Server error. Please try again.']);
}
