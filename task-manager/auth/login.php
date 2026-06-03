<?php

/**
 * Login page — auth/login.php
 *
 * Accepts either a username or email so users don't need to remember which
 * one they signed up with.
 *
 * Security notes:
 *   - Passwords are verified with password_verify() against a bcrypt hash —
 *     the raw password is never stored or compared as plain text.
 *   - The error message is deliberately vague ("Invalid username/email or
 *     password") so an attacker cannot tell whether the account exists.
 *   - All user-supplied values echoed into HTML are wrapped in htmlspecialchars()
 *     to prevent Cross-Site Scripting (XSS) attacks.
 */

session_start();

// If already logged in, skip the login page entirely.
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }

require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifier = trim($_POST['identifier'] ?? '');

    $password   = $_POST['password'] ?? '';

    if (!$identifier || !$password) {

        $error = 'Please enter your credentials.';

    } else {

        $db   = getDB();

        // Accept login by email OR username in one query using OR.
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? OR username = ?');

        $stmt->execute([$identifier, $identifier]);

        $user = $stmt->fetch();

        // password_verify() compares the submitted password against the stored
        // bcrypt hash. It is timing-safe, so it cannot leak info via timing attacks.
        if ($user && password_verify($password, $user['password_hash'])) {

            // Regenerate the session ID to prevent session fixation attacks.
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];

            $_SESSION['username'] = $user['username'];

            header('Location: ../dashboard.php');

            exit;

        } else {

            $error = 'Invalid username/email or password.';

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In – TaskFlow</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body class="auth-body">

    <div class="auth-card">

        <div class="auth-logo">

            <div class="auth-logo-icon">✓</div>

            <h1 class="auth-logo-name">TaskFlow</h1>

        </div>

        <h2 class="auth-title">Welcome back</h2>

        <p class="auth-subtitle">Sign in to your account to continue</p>

        <?php if ($error): ?>

            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>

        <?php endif; ?>

        <form method="POST" class="auth-form">

            <div class="form-group">

                <label class="form-label">Username or Email</label>

                <input type="text" name="identifier" class="form-input" placeholder="johndoe or john@example.com"

                       value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required autofocus>

            </div>

            <div class="form-group">

                <label class="form-label">Password</label>

                <input type="password" name="password" class="form-input" placeholder="Your password" required>

            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign In</button>

        </form>

        <p class="auth-switch">Don't have an account? <a href="signup.php">Create one</a></p>

    </div>

</body>

</html>
