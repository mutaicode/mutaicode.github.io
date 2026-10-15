<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($newPassword) < 10) {
        $errors[] = 'Choose a new password with at least 10 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'The new password and confirmation do not match.';
    }
    if ($currentPassword !== '' && $currentPassword === $newPassword) {
        $errors[] = 'Your new password must be different from the current password.';
    }

    if (!$errors && $user) {
        $stmt = db()->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute([
            ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int) $user['id'],
        ]);
        session_regenerate_id(true);
        $success = true;
        $user = currentUser();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | CYA Connect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<header class="topbar">
    <div class="container nav">
        <a href="dashboard.php" class="brand" style="text-decoration:none"><div class="brand-mark">C</div><span>CYA Connect</span></a>
        <nav class="nav-links" data-nav-menu>
            <a href="dashboard.php">Dashboard</a>
            <a href="change_password.php" class="active">Change password</a>
            <a href="logout.php" class="button secondary">Logout</a>
        </nav>
    </div>
</header>
<main class="page-shell">
    <div class="container" style="max-width:680px">
        <section class="dashboard-card card-section">
            <p class="eyebrow">Account security</p>
            <h2>Change your password</h2>
            <p>Use a unique password with at least 10 characters.</p>
            <?php if ($success): ?><div class="notice success">Password changed successfully.</div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="notice danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?>
            <form method="post" action="change_password.php" autocomplete="off">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="field full"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                    <div class="field full"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" minlength="10" autocomplete="new-password" required></div>
                    <div class="field full"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required></div>
                </div>
                <div class="action-row" style="margin-top:20px"><button class="button primary" type="submit">Update password</button><a href="dashboard.php" class="button secondary">Back to dashboard</a></div>
            </form>
        </section>
    </div>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
