<?php

/**
 * Sign-up page — auth/signup.php
 *
 * Validates all input server-side before creating the account.
 * Security notes:
 *   - PASSWORD_DEFAULT uses bcrypt; PHP automatically upgrades the algorithm
 *     in future versions, so stored hashes stay secure over time.
 *   - We check for duplicate username/email before inserting so the UNIQUE
 *     constraint in the DB is a backstop, not the first line of defence.
 */

session_start();

if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }

require_once '../includes/db.php';

$error = '';

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');

    $email    = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';

    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$username || !$email || !$password) {

        $error = 'All fields are required.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        // PHP's built-in email validator checks RFC 5322 format.
        $error = 'Invalid email address.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters.';

    } elseif ($password !== $confirm) {

        $error = 'Passwords do not match.';

    } else {

        $db = getDB();

        // Reject duplicates with a friendly message before hitting the DB constraint.
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');

        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {

            $error = 'Username or email already exists.';

        } else {

            // password_hash with PASSWORD_DEFAULT produces a bcrypt hash.
            // The hash includes its own salt, so two calls on the same password
            // produce different hashes — rainbow tables are defeated.
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');

            $stmt->execute([$username, $email, $hash]);

            $success = 'Account created! You can now sign in.';

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up – TaskFlow</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body class="auth-body">

    <div class="auth-card">

        <div class="auth-logo">

            <div class="auth-logo-icon">✓</div>

            <h1 class="auth-logo-name">TaskFlow</h1>

        </div>

        <h2 class="auth-title">Create your account</h2>

        <p class="auth-subtitle">Start organizing your tasks today</p>

        <?php if ($error): ?>

            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>

        <?php endif; ?>

        <?php if ($success): ?>

            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>

        <?php endif; ?>

        <form method="POST" class="auth-form">

            <div class="form-group">

                <label class="form-label">Username</label>

                <input type="text" name="username" class="form-input" placeholder="johndoe"

                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            </div>

            <div class="form-group">

                <label class="form-label">Email address</label>

                <input type="email" name="email" class="form-input" placeholder="john@example.com"

                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            </div>

            <div class="form-group">

                <label class="form-label">Password</label>

                <input type="password" name="password" class="form-input" placeholder="Min. 6 characters" required>

            </div>

            <div class="form-group">

                <label class="form-label">Confirm password</label>

                <input type="password" name="confirm_password" class="form-input" placeholder="Repeat your password" required>

            </div>

            <button type="submit" class="btn btn-primary btn-full">Create Account</button>

        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>

    </div>

</body>

</html>
