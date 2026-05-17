<?php
session_start();
header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'auth/login.php'));
exit;
