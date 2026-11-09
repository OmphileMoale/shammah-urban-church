<?php
/* GET api/events.php   → { ok, events: [ {id,title,date,time,venue,category,description,capacity,going,my_rsvp} ] }
   Same EVENT table the website's Events page uses. */
require __DIR__ . '/_bootstrap.php';
api_method('GET');
$m = api_user($pdo);

$st = $pdo->prepare('
    SELECT e.event_id, e.title, e.event_date, e.time_str, e.venue, e.category, e.description, e.capacity,
           (SELECT COUNT(*) FROM RSVP r WHERE r.event_id = e.event_id AND r.status = "confirmed") AS going,
           (SELECT r.status FROM RSVP r WHERE r.event_id = e.event_id AND r.member_id = ?)        AS my_rsvp
    FROM   EVENT e
    WHERE  e.is_active = 1
    ORDER  BY e.event_date');
$st->execute([$m['member_id']]);

$events = array_map(fn($e) => [
    'id'          => (int) $e['event_id'],
    'title'       => $e['title'],
    'date'        => date('D j M Y', strtotime($e['event_date'])),
    'time'        => $e['time_str'],
    'venue'       => $e['venue'],
    'category'    => $e['category'],
    'description' => (string) $e['description'],
    'capacity'    => (int) $e['capacity'],
    'going'       => (int) $e['going'],
    'my_rsvp'     => $e['my_rsvp'] === 'confirmed',
], $st->fetchAll());
api_ok(['events' => $events]);
