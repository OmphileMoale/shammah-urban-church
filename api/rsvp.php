<?php
/* POST api/rsvp.php   { event_id, cancel?: true }
   Same rules as the website's rsvp_handler.php (capacity check). */
require __DIR__ . '/_bootstrap.php';
api_method('POST');
$m = api_user($pdo);

$eventId = (int) (api_input()['event_id'] ?? 0);
$cancel  = !empty(api_input()['cancel']);

$st = $pdo->prepare('
    SELECT e.title, e.capacity,
           (SELECT COUNT(*) FROM RSVP r WHERE r.event_id = e.event_id AND r.status = "confirmed") AS going
    FROM EVENT e WHERE e.event_id = ? AND e.is_active = 1');
$st->execute([$eventId]);
$ev = $st->fetch();
if (!$ev) api_fail('Event not found.', 404);

if ($cancel) {
    $pdo->prepare('UPDATE RSVP SET status = "cancelled" WHERE member_id = ? AND event_id = ?')->execute([$m['member_id'], $eventId]);
    api_ok(['msg' => "Your RSVP for {$ev['title']} was cancelled."]);
}

$mine = $pdo->prepare('SELECT status FROM RSVP WHERE member_id = ? AND event_id = ?');
$mine->execute([$m['member_id'], $eventId]);
if ($mine->fetchColumn() !== 'confirmed' && (int) $ev['going'] >= (int) $ev['capacity']) {
    api_fail('Sorry, this event is full.', 409);
}
$pdo->prepare('INSERT INTO RSVP (member_id, event_id, status, created_at) VALUES (?, ?, "confirmed", NOW())
               ON DUPLICATE KEY UPDATE status = "confirmed"')->execute([$m['member_id'], $eventId]);
api_ok(['msg' => "You're attending {$ev['title']}. See you there!"]);
