<?php
/* ═══════════════════════════════════════
   ministry_join.php  —  JOIN / LEAVE A MINISTRY
   Called by js/ministries.js:
     POST JSON { ministry_id, action: "join" | "leave" }
     header    X-CSRF-Token
   Reply: { success: true, member_count: N }  or  { success: false, message }

   Joining saves a row in MINISTRY_MEMBER and leaving switches it off,
   so the member numbers on ministries.php change as people join.
═══════════════════════════════════════ */
session_start();
header('Content-Type: application/json');

function reply(bool $ok, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $ok] + $extra);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(false, ['message' => 'Invalid request.'], 405);
if (empty($_SESSION['member_id']))         reply(false, ['message' => 'Please log in to join a ministry.'], 401);

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    reply(false, ['message' => 'Security token mismatch. Please refresh the page.'], 403);
}

$in          = json_decode((string) file_get_contents('php://input'), true) ?: [];
$ministry_id = (int) ($in['ministry_id'] ?? 0);
$action      = $in['action'] ?? '';
if ($ministry_id < 1 || !in_array($action, ['join', 'leave'], true)) {
    reply(false, ['message' => 'Invalid request.'], 400);
}

require_once __DIR__ . '/includes/db.php';

try {
    $st = $pdo->prepare('SELECT 1 FROM MINISTRY WHERE ministry_id = ? AND is_active = 1');
    $st->execute([$ministry_id]);
    if (!$st->fetchColumn()) reply(false, ['message' => 'That ministry is not available.'], 404);

    $member_id = (int) $_SESSION['member_id'];

    if ($action === 'join') {
        $pdo->prepare('INSERT INTO MINISTRY_MEMBER (member_id, ministry_id, date_joined, is_active)
                       VALUES (?, ?, CURDATE(), 1)
                       ON DUPLICATE KEY UPDATE is_active = 1')
            ->execute([$member_id, $ministry_id]);
    } else {
        $pdo->prepare('UPDATE MINISTRY_MEMBER SET is_active = 0 WHERE member_id = ? AND ministry_id = ?')
            ->execute([$member_id, $ministry_id]);
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM MINISTRY_MEMBER mm JOIN MEMBER m ON m.member_id = mm.member_id
                         WHERE mm.ministry_id = ? AND mm.is_active = 1 AND m.is_active = 1');
    $st->execute([$ministry_id]);
    reply(true, ['member_count' => (int) $st->fetchColumn()]);
} catch (Throwable $e) {
    error_log('Shammah ministry_join failed: ' . $e->getMessage());
    reply(false, ['message' => 'Something went wrong. Please try again.'], 500);
}