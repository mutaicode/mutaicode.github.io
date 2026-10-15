<?php
require_once __DIR__ . '/config.php';

$errors = [];

if (currentUser()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $churchName = trim($_POST['church_name'] ?? '');
    $roleName = trim($_POST['role_name'] ?? '');
    $accountRole = $_POST['account_role'] ?? 'youth';
    $profilePhotoPath = null;

    if ($name === '' || $email === '' || $password === '' || $phoneNumber === '' || $churchName === '' || $roleName === '') {
        $errors[] = 'All fields are required.';
    }

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your profile photo.';
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $mimeType = validateMimeType($_FILES['profile_photo']['tmp_name']);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $errors[] = 'Profile photo must be a JPG, PNG, or WEBP image.';
            } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Profile photo must be smaller than 2MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/profile_photos';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
                $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $safeName;

                if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    $errors[] = 'Unable to save the uploaded profile photo.';
                } else {
                    $profilePhotoPath = 'uploads/profile_photos/' . $safeName;
                }
            }
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (!in_array($accountRole, ['youth', 'leader'], true)) {
        $accountRole = 'youth';
    }

    if (empty($errors)) {
        $check = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);

        if ($check->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (name, email, password, phone_number, church_name, role_name, account_role, is_approved, profile_photo) VALUES (:name, :email, :password, :phone_number, :church_name, :role_name, :account_role, 0, :profile_photo)');
            $stmt->execute([
                ':name'         => $name,
                ':email'        => $email,
                ':password'     => $hash,
                ':phone_number' => $phoneNumber,
                ':church_name'  => $churchName,
                ':role_name'    => $roleName,
                ':account_role' => $accountRole,
                ':profile_photo'=> $profilePhotoPath,
            ]);

            $_SESSION['registration_notice'] = $accountRole === 'youth'
                ? 'Your registration has been submitted. Please wait for the leader to approve your account before you can log in.'
                : 'Your leader registration has been submitted. Please wait for the administrator to approve your account before you can log in.';

            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CYA Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="brand" style="margin-bottom: 20px;">
                <div class="brand-mark">C</div>
                <span>CYA Connect</span>
            </div>
            <h2>Create your youth account</h2>
            <p style="color: var(--muted); margin-bottom: 24px;">Join youths from various churches and assign your ministry role.</p>

            <?php foreach ($errors as $message): ?>
                <div class="notice danger" data-autodismiss="6000"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>

            <form method="post" action="register.php" enctype="multipart/form-data" data-loading>
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="field full">
                        <label for="profile_photo">Profile photo</label>
                        <input id="profile_photo" name="profile_photo" type="file" accept="image/*">
                    </div>
                    <div class="field full">
                        <label for="name">Full name</label>
                        <input id="name" name="name" type="text" required placeholder="Jane Doe" autocomplete="name">
                    </div>
                    <div class="field full">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" required placeholder="you@example.com" autocomplete="email">
                    </div>
                    <div class="field full">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required placeholder="At least 6 characters" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="phone_number">Phone number</label>
                        <input id="phone_number" name="phone_number" type="tel" required placeholder="+2547..." autocomplete="tel">
                    </div>
                    <div class="field">
                        <label for="church_name">Church name</label>
                        <input id="church_name" name="church_name" type="text" required placeholder="AIC KBN">
                    </div>
                    <div class="field">
                        <label for="role_name">Assigned role</label>
                        <input id="role_name" name="role_name" type="text" required placeholder="Music Team, Prayer Team, Media...">
                    </div>
                    <div class="field full">
                        <label for="account_role">Account type</label>
                        <select id="account_role" name="account_role">
                            <option value="youth">Youth</option>
                            <option value="leader">Youth Leader</option>
                        </select>
                    </div>
                </div>
                <div class="action-row" style="margin-top: 24px;">
                    <button class="button primary" type="submit">Register</button>
                    <a href="login.php" class="button secondary">Already have an account?</a>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>