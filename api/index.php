<?php
/* api/index.php — quick check that the app can reach the server.
   Open http://<your-PC-IP>/shammah-urban-church/api/ on the phone's browser. */
require __DIR__ . '/_bootstrap.php';
api_ok(['service' => 'Shammah Urban Church API', 'database' => 'connected', 'time' => date('c')]);
