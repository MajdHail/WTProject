<?php

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {

    header('Location: ' . (strpos($_SERVER['PHP_SELF'], '/auth/') !== false ? 'login.php' : 'auth/login.php'));

    exit;

}
