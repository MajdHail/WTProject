<?php
session_start();
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
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
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
