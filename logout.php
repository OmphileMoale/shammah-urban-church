<?php
/* ═══════════════════════════════════════
   logout.php  –  SESSION DESTROY

   HOW IT WORKS:
   1. session_start()  – opens the session
                         so we can destroy it
   2. session_unset()  – removes all session
                         variables ($_SESSION)
   3. session_destroy()– deletes the session
                         file on the server
   4. setcookie()      – clears the session
                         cookie in the browser
   5. header()         – redirects to home page

   WHY ALL FOUR STEPS?
   session_unset() clears the data.
   session_destroy() removes the server file.
   setcookie() removes the browser cookie.
   Together they ensure no trace of the
   session remains client- or server-side.
═══════════════════════════════════════ */
session_start();

// 1. Remove all session variables
session_unset();

// 2. Destroy the session on the server
session_destroy();

// 3. Expire the session cookie in the browser
//    by setting its expiry time to the past
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 4. Redirect to home page
header('Location: index.php');
exit;
