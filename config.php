<?php
session_start();

const APP_NAME = 'CYA Connect';

// MySQL hosting: uncomment and fill in these constants with values from your
// hosting control panel. Leave them unset to use the local SQLite database.
// Never commit real database credentials to a public repository.
// define('DB_HOST', 'your-host-provided-database-host');
// define('DB_NAME', 'your_database_name');
// define('DB_USER', 'your_database_username');
// define('DB_PASS', 'your_database_password');

// InfinityFree Database Configuration (uncomment to use MySQL)

function getDatabaseConfig(): array
{
    $mysqlHost = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '');
    $mysqlDb   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : '');
    $mysqlUser = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : '');
    $mysqlPass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

    if ($mysqlHost !== '' && $mysqlDb !== '' && $mysqlUser !== '') {
        return [
            'driver' => 'mysql',
            'host'   => $mysqlHost,
            'dbname' => $mysqlDb,
            'user'   => $mysqlUser,
            'pass'   => $mysqlPass,
        ];
    }

    return [
        'driver' => 'sqlite',
        'path'   => __DIR__ . '/database/church_youth.db',
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $config = getDatabaseConfig();

    try {
        if ($config['driver'] === 'mysql') {
            $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('SET NAMES utf8mb4');
        } else {
            $dbDir = __DIR__ . '/database';
            if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);

            $pdo = new PDO('sqlite:' . $dbDir . '/church_youth.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        die('Database connection failed. Please check your configuration.');
    }

    initializeDatabase($pdo);
    return $pdo;
}

/* ---------- Security & utility helpers ---------- */

function sanitizeFilename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return trim($name, '._-') ?: 'file';
}

function validateMimeType(string $filePath): ?string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if ($mime !== false) return $mime;
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($filePath);
        if ($info && !empty($info['mime'])) return $info['mime'];
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $map = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    return $map[$ext] ?? null;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

function validateCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('Security check failed. Please try again.', 'error');
        $fallback = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        redirect($fallback);
    }
}

/* ---------- Flash / toast ---------- */

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function getFlash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- Schema helpers ---------- */

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    return in_array($column, $stmt->fetchAll(PDO::FETCH_COLUMN, 1), true);
}

function initializeDatabase(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password TEXT NOT NULL,
                phone_number VARCHAR(255) NULL,
                church_name VARCHAR(255) NOT NULL,
                role_name VARCHAR(255) NOT NULL,
                account_role VARCHAR(50) NOT NULL DEFAULT 'youth',
                is_approved TINYINT(1) NOT NULL DEFAULT 0,
                profile_photo TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS events (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                event_date DATE NOT NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                venue VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'planned',
                leader_name VARCHAR(255) NULL,
                target_attendees INT DEFAULT 0,
                link_url TEXT NULL,
                link_label VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS attendance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_id INT NOT NULL,
                user_id INT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'registered',
                notes TEXT NULL,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_attendance (event_id, user_id),
                CONSTRAINT fk_attendance_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                CONSTRAINT fk_attendance_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS feedback (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                suggestion TEXT NOT NULL,
                rating INT NOT NULL DEFAULT 0,
                mood VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_feedback_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                CONSTRAINT fk_feedback_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS gallery_photos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                image_path TEXT NOT NULL,
                caption TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_gallery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL;
    } else {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                phone_number TEXT,
                church_name TEXT NOT NULL,
                role_name TEXT NOT NULL,
                account_role TEXT NOT NULL DEFAULT 'youth',
                is_approved INTEGER NOT NULL DEFAULT 0,
                profile_photo TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                event_date TEXT NOT NULL,
                start_date TEXT,
                end_date TEXT,
                venue TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'planned',
                leader_name TEXT,
                target_attendees INTEGER DEFAULT 0,
                link_url TEXT,
                link_label TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'registered',
                notes TEXT,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(event_id, user_id),
                FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id)  REFERENCES users(id)  ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                comment TEXT NOT NULL,
                suggestion TEXT NOT NULL,
                rating INTEGER NOT NULL DEFAULT 0,
                mood TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id)  REFERENCES users(id)  ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS gallery_photos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                image_path TEXT NOT NULL,
                caption TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        SQL;
    }

    // Execute each CREATE TABLE separately for compatibility with PDO MySQL,
    // where multi-statement execution may be disabled by the host.
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }

    ensureUserProfilePhotoColumn($pdo);
    ensureUserApprovalColumn($pdo);
    ensureEventDateColumns($pdo);
    ensureEventLinkColumns($pdo);
    seedDemoData($pdo);
}

function ensureUserProfilePhotoColumn(PDO $pdo): void
{
    if (!columnExists($pdo, 'users', 'profile_photo')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_photo TEXT');
    }
}

function ensureUserApprovalColumn(PDO $pdo): void
{
    if (!columnExists($pdo, 'users', 'phone_number')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN phone_number TEXT');
    }
    if (!columnExists($pdo, 'users', 'is_approved')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0');
    }
    $pdo->exec("UPDATE users SET is_approved = 1 WHERE LOWER(email) = 'admin2026@gmail.com'");
}

function ensureEventDateColumns(PDO $pdo): void
{
    if (!columnExists($pdo, 'events', 'start_date')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN start_date DATE NULL');
    }
    if (!columnExists($pdo, 'events', 'end_date')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN end_date DATE NULL');
    }
    $pdo->exec("UPDATE events SET start_date = COALESCE(start_date, event_date), end_date = COALESCE(end_date, event_date) WHERE start_date IS NULL OR end_date IS NULL");
}

function ensureEventLinkColumns(PDO $pdo): void
{
    if (!columnExists($pdo, 'events', 'link_url')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN link_url TEXT NULL');
    }
    if (!columnExists($pdo, 'events', 'link_label')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN link_label TEXT NULL');
    }
}

function seedDemoData(PDO $pdo): void
{
    // Create the initial administrator only when the account does not exist.
    // Never overwrite an existing password during ordinary page requests.
    $adminEmail = 'admin2026@gmail.com';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute([':email' => $adminEmail]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $adminHash = password_hash('ADMIN@2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone_number, church_name, role_name, account_role, is_approved) VALUES (:name, :email, :password, :phone_number, :church_name, :role_name, :account_role, 1)');
    $stmt->execute([
        ':name'         => 'Administrator',
        ':email'        => $adminEmail,
        ':password'     => $adminHash,
        ':phone_number' => '+254700000000',
        ':church_name'  => 'AIC KBN',
        ':role_name'    => 'System Administrator',
        ':account_role' => 'leader',
    ]);
}

/* ---------- Auth ---------- */

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) return null;

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        flash('Please log in to continue.', 'info');
        redirect('login.php');
    }
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function formatDate(?string $dateString): string
{
    if (empty($dateString)) return 'N/A';
    $ts = strtotime($dateString);
    return $ts !== false ? date('F j, Y', $ts) : 'N/A';
}

/* ---------- Event link helpers ---------- */

function safeEventLink(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') return null;

    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

function eventLinkLabel(?string $label, ?string $url): string
{
    $label = trim((string) $label);
    if ($label !== '') return $label;
    return 'Open link';
}

/* ---------- Event lifecycle ---------- */

function syncEventLifecycle(): void
{
    $pdo = db();
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    $pdo->prepare("UPDATE events SET status = 'completed'
                   WHERE end_date IS NOT NULL AND end_date < :today
                   AND LOWER(status) <> 'completed'")
        ->execute([':today' => $today]);

    $pdo->prepare("UPDATE events SET status = 'ongoing'
                   WHERE start_date IS NOT NULL AND end_date IS NOT NULL
                   AND start_date <= :today AND end_date >= :today
                   AND LOWER(status) <> 'ongoing'
                   AND LOWER(status) <> 'completed'")
        ->execute([':today' => $today]);

    $pdo->prepare("UPDATE events SET status = 'upcoming'
                   WHERE start_date IS NOT NULL AND start_date > :today
                   AND LOWER(status) NOT IN ('upcoming','cancelled')")
        ->execute([':today' => $today]);
}

function getAttendanceOptionsForEventStatus(?string $status): array
{
    if (strtolower((string) $status) === 'ongoing') {
        return ['present', 'absent', 'late', 'dayschooller'];
    }
    return ['registered'];
}

/* ---------- Event stats ---------- */

function getEventStats(?int $eventId = null): array
{
    if ($eventId === null) {
        $stmt = db()->query("SELECT
            COUNT(*) AS total_registrations,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status = 'late'    THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) AS registered_count
            FROM attendance");
        $stats = $stmt->fetch() ?: [];
        $target = null;
    } else {
        $stmt = db()->prepare("SELECT
            COUNT(*) AS total_registrations,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status = 'late'    THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) AS registered_count
            FROM attendance WHERE event_id = :event_id");
        $stmt->execute([':event_id' => $eventId]);
        $stats = $stmt->fetch() ?: [];

        $t = db()->prepare('SELECT target_attendees FROM events WHERE id = :id LIMIT 1');
        $t->execute([':id' => $eventId]);
        $target = $t->fetchColumn();
    }

    $total      = (int) ($stats['total_registrations'] ?? 0);
    $present    = (int) ($stats['present_count'] ?? 0);
    $late       = (int) ($stats['late_count'] ?? 0);
    $absent     = (int) ($stats['absent_count'] ?? 0);
    $registered = (int) ($stats['registered_count'] ?? 0);

    $effective = $present + ($late * 0.75);
    $rate = $total > 0 ? round(($effective / $total) * 100, 1) : 0;

    if ($target !== null && (int) $target > 0) {
        $rate = min(round(($effective / (int) $target) * 100, 1), 100);
    }

    return [
        'total'           => $total,
        'present'         => $present,
        'late'            => $late,
        'absent'          => $absent,
        'registered'      => $registered,
        'attendance_rate' => $rate,
    ];
}

function getEventStatsBulk(array $eventIds): array
{
    if (empty($eventIds)) return [];
    $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

    $stmt = db()->prepare("SELECT event_id,
        COUNT(*) AS total_registrations,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'late'    THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) AS registered_count
        FROM attendance WHERE event_id IN ($placeholders) GROUP BY event_id");
    $stmt->execute($eventIds);
    $rows = $stmt->fetchAll();

    $targetsStmt = db()->prepare("SELECT id, target_attendees FROM events WHERE id IN ($placeholders)");
    $targetsStmt->execute($eventIds);
    $targets = [];
    foreach ($targetsStmt->fetchAll() as $r) $targets[(int) $r['id']] = (int) $r['target_attendees'];

    $out = [];
    foreach ($eventIds as $id) {
        $out[$id] = [
            'total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0,
            'registered' => 0, 'attendance_rate' => 0.0,
        ];
    }

    foreach ($rows as $r) {
        $eid = (int) $r['event_id'];
        $total = (int) $r['total_registrations'];
        $present = (int) $r['present_count'];
        $late = (int) $r['late_count'];
        $effective = $present + ($late * 0.75);
        $target = $targets[$eid] ?? 0;
        $rate = $target > 0
            ? min(round(($effective / $target) * 100, 1), 100)
            : ($total > 0 ? round(($effective / $total) * 100, 1) : 0);

        $out[$eid] = [
            'total' => $total, 'present' => $present, 'late' => $late,
            'absent' => (int) $r['absent_count'], 'registered' => (int) $r['registered_count'],
            'attendance_rate' => $rate,
        ];
    }

    return $out;
}

/* ---------- AI suggestions ---------- */

function getAiSuggestions(?int $eventId = null): array
{
    $stats = getEventStats($eventId);

    if ($eventId !== null) {
        $stmt = db()->prepare('SELECT rating, comment, suggestion FROM feedback WHERE event_id = :event_id');
        $stmt->execute([':event_id' => $eventId]);
        $feedbackRows = $stmt->fetchAll();
    } else {
        $feedbackRows = db()->query('SELECT rating, comment, suggestion FROM feedback')->fetchAll();
    }

    $averageRating = 0;
    if (!empty($feedbackRows)) {
        $sum = 0;
        foreach ($feedbackRows as $row) $sum += (int) $row['rating'];
        $averageRating = round($sum / count($feedbackRows), 1);
    }

    $suggestions = [];

    if ($stats['attendance_rate'] < 70) {
        $suggestions[] = 'Send reminder texts and devotional messages earlier to improve event attendance before the next youth gathering.';
    }
    if ($averageRating > 0 && $averageRating < 3.5) {
        $suggestions[] = 'Review the program flow: increase interaction time, shorten long segments, and add more youth-led activities.';
    }
    if (!empty($feedbackRows)) {
        $negativeKeywords = ['late', 'short', 'quiet', 'slow', 'confusing', 'crowded', 'too long'];
        $hits = 0;
        foreach ($feedbackRows as $row) {
            $text = strtolower($row['comment'] . ' ' . $row['suggestion']);
            foreach ($negativeKeywords as $kw) {
                if (str_contains($text, $kw)) { $hits++; break; }
            }
        }
        if ($hits > 0) {
            $suggestions[] = 'Most feedback highlights repeated improvement areas; consider simplifying logistics and increasing time for peer interaction.';
        }
    }
    if (empty($suggestions)) {
        $suggestions[] = 'The youth engagement is strong; maintain momentum with consistent follow-up, celebration moments, and team-led activities.';
    }

    return [
        'average_rating'  => $averageRating,
        'attendance_rate' => $stats['attendance_rate'],
        'suggestions'     => $suggestions,
    ];
}

/* ---------- Dashboard / admin summaries ---------- */

function getDashboardData(): array
{
    syncEventLifecycle();

    $pdo = db();
    $youthCount   = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE account_role = "youth"')->fetchColumn();
    $leaderCount  = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE account_role = "leader"')->fetchColumn();
    $eventCount   = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    $feedbackCount= (int) $pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
    $overall      = getEventStats();

    return [
        'youth_count'     => $youthCount,
        'leader_count'    => $leaderCount,
        'event_count'     => $eventCount,
        'feedback_count'  => $feedbackCount,
        'attendance_rate' => $overall['attendance_rate'],
    ];
}

function getAdminSummary(): array
{
    syncEventLifecycle();
    $pdo = db();
    return [
        'pending_leaders' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE account_role = "leader" AND is_approved = 0')->fetchColumn(),
        'pending_youths'  => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE account_role = "youth" AND is_approved = 0')->fetchColumn(),
        'upcoming_events' => (int) $pdo->query('SELECT COUNT(*) FROM events WHERE LOWER(status) = "upcoming"')->fetchColumn(),
        'ongoing_events'  => (int) $pdo->query('SELECT COUNT(*) FROM events WHERE LOWER(status) = "ongoing"')->fetchColumn(),
        'completed_events'=> (int) $pdo->query('SELECT COUNT(*) FROM events WHERE LOWER(status) = "completed"')->fetchColumn(),
    ];
}

/* ---------- Role checks ---------- */

function isApprovedYouth(?array $user = null): bool
{
    $user = $user ?? currentUser();
    return $user && $user['account_role'] === 'youth' && (int) $user['is_approved'] === 1;
}

function isLeader(): bool
{
    $user = currentUser();
    return $user && $user['account_role'] === 'leader' && (int) $user['is_approved'] === 1;
}

function isAdmin(): bool
{
    $user = currentUser();
    if (!$user) return false;

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $approved = (int) ($user['is_approved'] ?? 0) === 1;

    // Never grant admin access based on name or role text supplied at registration.
    return $approved && (
        $email === 'admin2026@gmail.com'
        || ($user['account_role'] ?? '') === 'admin'
    );
}

/* ---------- Listings ---------- */

function listEvents(): array
{
    syncEventLifecycle();
    return db()->query('SELECT e.*, u.name AS leader_name_full FROM events e LEFT JOIN users u ON u.id = e.created_by ORDER BY e.event_date ASC')->fetchAll();
}

function listUsers(): array
{
    return db()->query('SELECT * FROM users ORDER BY account_role DESC, is_approved ASC, created_at DESC')->fetchAll();
}

function listYouthUsers(): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE account_role = :account_role ORDER BY is_approved ASC, created_at DESC, name ASC');
    $stmt->execute([':account_role' => 'youth']);
    return $stmt->fetchAll();
}

function listPendingLeaders(): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE account_role = :account_role AND COALESCE(is_approved, 0) = 0 ORDER BY created_at DESC, name ASC');
    $stmt->execute([':account_role' => 'leader']);
    return $stmt->fetchAll();
}

function listPendingYouths(): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE account_role = :account_role AND COALESCE(is_approved, 0) = 0 ORDER BY created_at DESC, name ASC');
    $stmt->execute([':account_role' => 'youth']);
    return $stmt->fetchAll();
}

function listChurchYouthCounts(): array
{
    $stmt = db()->prepare('SELECT church_name, COUNT(*) AS youth_count FROM users WHERE account_role = :account_role GROUP BY church_name ORDER BY youth_count DESC, church_name ASC');
    $stmt->execute([':account_role' => 'youth']);
    return $stmt->fetchAll();
}

function getChurchCount(): int
{
    return (int) db()->query('SELECT COUNT(DISTINCT church_name) FROM users WHERE account_role = "youth"')->fetchColumn();
}

function listFeedback(): array
{
    return db()->query('SELECT f.*, u.name AS youth_name, e.title AS event_title FROM feedback f INNER JOIN users u ON u.id = f.user_id INNER JOIN events e ON e.id = f.event_id ORDER BY f.created_at DESC')->fetchAll();
}

function listGalleryPhotos(): array
{
    return db()->query('SELECT gp.*, u.name, u.church_name FROM gallery_photos gp INNER JOIN users u ON u.id = gp.user_id ORDER BY gp.created_at DESC')->fetchAll();
}

function getAttendanceForEvents(array $eventIds): array
{
    if (empty($eventIds)) return [];
    $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

    $stmt = db()->prepare("SELECT a.*, u.name, u.church_name, u.role_name
                           FROM attendance a
                           INNER JOIN users u ON u.id = a.user_id
                           WHERE a.event_id IN ($placeholders)
                           ORDER BY u.name ASC");
    $stmt->execute($eventIds);

    $out = [];
    foreach ($eventIds as $id) $out[$id] = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['event_id']][] = $row;
    }
    return $out;
}

function eventExists(int $eventId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM events WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    return (bool) $stmt->fetchColumn();
}

/* ---------- Uploads ---------- */

function uploadGalleryPhoto(int $userId, string $caption = ''): ?string
{
    if (!isset($_FILES['gallery_photo']) || $_FILES['gallery_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['gallery_photo']['error'] !== UPLOAD_ERR_OK) {
        flash('There was a problem uploading your photo.', 'error');
        return null;
    }

    $mime = validateMimeType($_FILES['gallery_photo']['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        flash('Photo must be a JPG, PNG, GIF, or WEBP image.', 'error');
        return null;
    }
    if ($_FILES['gallery_photo']['size'] > 5 * 1024 * 1024) {
        flash('Photo must be smaller than 5MB.', 'error');
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/gallery_photos';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($_FILES['gallery_photo']['tmp_name'], $target)) {
        flash('Unable to save the uploaded photo.', 'error');
        return null;
    }

    $imagePath = 'uploads/gallery_photos/' . $safeName;
    $stmt = db()->prepare('INSERT INTO gallery_photos (user_id, image_path, caption) VALUES (:user_id, :image_path, :caption)');
    $stmt->execute([
        ':user_id'    => $userId,
        ':image_path' => $imagePath,
        ':caption'    => $caption,
    ]);
    return $imagePath;
}