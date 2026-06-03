<?php

/**
 * Authentication guard — included at the top of every protected page.
 *
 * If there is no active session with a user_id, the user is redirected to the
 * login page. The redirect path is adjusted based on the current directory
 * so it works for both top-level pages and pages inside /auth/.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {

    header('Location: ' . (strpos($_SERVER['PHP_SELF'], '/auth/') !== false ? 'login.php' : 'auth/login.php'));

    exit;

}
