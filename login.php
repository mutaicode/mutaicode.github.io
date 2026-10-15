<?php
require_once __DIR__ . '/config.php';

$errors = [];
$successMessage = $_SESSION['registration_notice'] ?? '';
unset($_SESSION['registration_notice']);

if (currentUser()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please fill in all required login details.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Rotate the session ID after successful authentication.
            session_regenerate_id(true);
            if ((int) $user['is_approved'] !== 1) {
                $errors[] = $user['account_role'] === 'leader'
                    ? 'Your leader account is still pending admin approval.'
                    : 'Your youth registration is still pending approval by the leader.';
            } else {
                $_SESSION['user_id'] = (int) $user['id'];
                flash('Welcome back, ' . $user['name'] . '!', 'success');
                redirect('dashboard.php');
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CYA Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="brand" style="margin-bottom: 20px;">
                <div class="brand-mark">C</div>
                <span>CYA Connect</span>
            </div>
            <h2>Welcome back</h2>
            <p style="color: var(--muted); margin-bottom: 24px;">Sign in to your youth management dashboard.</p>

            <?php if ($successMessage !== ''): ?>
                <div class="notice success" data-autodismiss="6000"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $message): ?>
                <div class="notice danger" data-autodismiss="6000"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>

            <form method="post" action="login.php" data-loading>
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="field full">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" required placeholder="you@example.com" autocomplete="email">
                    </div>
                    <div class="field full">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required placeholder="Your password" autocomplete="current-password">
                    </div>
                </div>
                <div class="action-row" style="margin-top: 24px;">
                    <button class="button primary" type="submit">Login</button>
                    <a href="index.php" class="button secondary">Back home</a>
                </div>
            </form>

            <p style="margin-top: 22px; color: var(--muted);">
                New here? <a href="register.php" class="muted-link">Create an account</a>
            </p>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>