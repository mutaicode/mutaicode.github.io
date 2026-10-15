<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    validateCsrf();
    $form = $_POST['form_type'];

    if ($form === 'upload_gallery' && isApprovedYouth($user)) {
        $caption = trim($_POST['caption'] ?? '');
        if (uploadGalleryPhoto((int) $user['id'], $caption)) {
            flash('Photo uploaded to the gallery.', 'success');
        }
    }

    if ($form === 'approve_leader' && isAdmin()) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = db()->prepare('UPDATE users SET is_approved = 1 WHERE id = :id AND account_role = :role');
            $stmt->execute([':id' => $userId, ':role' => 'leader']);
            flash('Leader approved.', 'success');
        }
    }

    if ($form === 'reject_leader' && isAdmin()) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) $user['id']) {
            $stmt = db()->prepare('DELETE FROM users WHERE id = :id AND account_role = :role AND is_approved = 0');
            $stmt->execute([':id' => $userId, ':role' => 'leader']);
            flash('Leader registration rejected.', 'info');
        }
    }

    if ($form === 'delete_user' && isAdmin()) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) $user['id']) {
            db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
            flash('User account deleted.', 'info');
        }
    }

    if ($form === 'approve_youth' && isLeader()) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = db()->prepare('UPDATE users SET is_approved = 1 WHERE id = :id AND account_role = :role');
            $stmt->execute([':id' => $userId, ':role' => 'youth']);
            flash('Youth approved.', 'success');
        }
    }

    if ($form === 'reject_youth' && isLeader()) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) $user['id']) {
            $stmt = db()->prepare('DELETE FROM users WHERE id = :id AND account_role = :role AND is_approved = 0');
            $stmt->execute([':id' => $userId, ':role' => 'youth']);
            flash('Youth registration rejected.', 'info');
        }
    }

    if ($form === 'update_event' && isAdmin()) {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? $_POST['event_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? $startDate));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $status = strtolower(trim((string) ($_POST['status'] ?? 'upcoming')));
        $target = (int) ($_POST['target_attendees'] ?? 0);

                $linkUrl   = safeEventLink($_POST['link_url'] ?? '');
        $linkLabel = trim((string) ($_POST['link_label'] ?? ''));

        if ($eventId > 0 && eventExists($eventId) && $title !== '' && $startDate !== '' && $venue !== '') {
            if ($status === 'planned') $status = 'upcoming';
            $stmt = db()->prepare('UPDATE events SET title = :title, description = :description, event_date = :event_date, start_date = :start_date, end_date = :end_date, venue = :venue, status = :status, target_attendees = :target, link_url = :link_url, link_label = :link_label WHERE id = :id');
            $stmt->execute([
                ':title' => $title, ':description' => $description,
                ':event_date' => $startDate, ':start_date' => $startDate, ':end_date' => $endDate,
                ':venue' => $venue, ':status' => $status, ':target' => $target,
                ':link_url' => $linkUrl, ':link_label' => $linkLabel !== '' ? $linkLabel : null,
                ':id' => $eventId,
            ]);
            flash('Event updated.', 'success');
        }
    }

    if ($form === 'create_event' && isLeader()) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startDate = trim($_POST['start_date'] ?? $_POST['event_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? $startDate);
        $venue = trim($_POST['venue'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'upcoming'));
        if ($status === 'planned') $status = 'upcoming';
        $target = (int) ($_POST['target_attendees'] ?? 0);

                $linkUrl   = safeEventLink($_POST['link_url'] ?? '');
        $linkLabel = trim((string) ($_POST['link_label'] ?? ''));

        if ($title !== '' && $startDate !== '' && $venue !== '') {
            $stmt = db()->prepare('INSERT INTO events (title, description, event_date, start_date, end_date, venue, status, leader_name, target_attendees, created_by, link_url, link_label) VALUES (:title, :description, :event_date, :start_date, :end_date, :venue, :status, :leader_name, :target, :created_by, :link_url, :link_label)');
            $stmt->execute([
                ':title' => $title, ':description' => $description,
                ':event_date' => $startDate, ':start_date' => $startDate, ':end_date' => $endDate,
                ':venue' => $venue, ':status' => $status,
                ':leader_name' => $user['name'], ':target' => $target, ':created_by' => $user['id'],
                ':link_url' => $linkUrl, ':link_label' => $linkLabel !== '' ? $linkLabel : null,
            ]);
            flash('Event created.', 'success');
        }
    }

    if ($form === 'register_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $status = trim($_POST['attendance_status'] ?? 'registered');
        $notes = trim($_POST['notes'] ?? '');

        if ($eventId > 0 && eventExists($eventId)) {
            $eventRow = db()->prepare('SELECT status FROM events WHERE id = :id LIMIT 1');
            $eventRow->execute([':id' => $eventId]);
            $allowed = getAttendanceOptionsForEventStatus($eventRow->fetchColumn() ?: 'upcoming');
            if (!in_array($status, $allowed, true)) $status = $allowed[0];

            if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $attendanceSql = 'INSERT INTO attendance (event_id, user_id, status, notes) VALUES (:event_id, :user_id, :status, :notes) ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)';
            } else {
                $attendanceSql = 'INSERT INTO attendance (event_id, user_id, status, notes) VALUES (:event_id, :user_id, :status, :notes) ON CONFLICT(event_id, user_id) DO UPDATE SET status = excluded.status, notes = excluded.notes';
            }
            $stmt = db()->prepare($attendanceSql);
            $stmt->execute([
                ':event_id' => $eventId, ':user_id' => $user['id'],
                ':status' => $status, ':notes' => $notes,
            ]);
            flash('Attendance saved.', 'success');
        }
    }

    if ($form === 'submit_feedback') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $suggestion = trim($_POST['suggestion'] ?? '');
        $rating = (int) ($_POST['rating'] ?? 0);
        $mood = trim($_POST['mood'] ?? 'positive');

        if ($eventId > 0 && eventExists($eventId) && $comment !== '' && $suggestion !== '') {
            $stmt = db()->prepare('INSERT INTO feedback (event_id, user_id, comment, suggestion, rating, mood) VALUES (:event_id, :user_id, :comment, :suggestion, :rating, :mood)');
            $stmt->execute([
                ':event_id' => $eventId, ':user_id' => $user['id'],
                ':comment' => $comment, ':suggestion' => $suggestion,
                ':rating' => $rating, ':mood' => $mood,
            ]);
            flash('Thank you for your feedback.', 'success');
        }
    }

    if ($form === 'update_attendance' && isLeader()) {
        $attendanceId = (int) ($_POST['attendance_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'registered');
        if ($attendanceId > 0) {
            $stmt = db()->prepare('UPDATE attendance SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $attendanceId]);
            flash('Attendance updated.', 'success');
        }
    }

    redirect('dashboard.php');
}

syncEventLifecycle();

$events = listEvents() ?: [];
$youths = listUsers() ?: [];
$pendingLeaders = isAdmin() ? (listPendingLeaders() ?: []) : [];
$pendingYouths = isLeader() ? (listPendingYouths() ?: []) : [];
$churchYouthCounts = isLeader() ? (listChurchYouthCounts() ?: []) : [];
$totalChurches = isLeader() ? (getChurchCount() ?: 0) : 0;
$feedback = listFeedback() ?: [];
$dashboard = getDashboardData() ?: [];
$adminSummary = isAdmin() ? (getAdminSummary() ?: []) : [];
$ai = getAiSuggestions() ?: [];

$eventIds = array_map(fn($e) => (int) $e['id'], $events);
$eventStats = getEventStatsBulk($eventIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CYA Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <div class="container nav">
            <a href="index.php" class="brand" style="text-decoration:none;">
                <div class="brand-mark">C</div>
                <span>CYA Connect</span>
            </a>
            <button class="nav-toggle" type="button" data-toggle-menu aria-expanded="false" aria-label="Toggle menu"><span></span></button>
            <nav class="nav-links" data-nav-menu>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="events.php">Events</a>
                <?php if (isLeader()): ?><a href="youths.php">Youths</a><?php endif; ?>
                <a href="feedback.php">Feedback</a>
                <a href="change_password.php">Change password</a>
                <a href="logout.php" class="button secondary">Logout</a>
            </nav>
        </div>
    </header>

    <?php foreach (getFlash() as $f): ?>
        <span data-flash="<?php echo htmlspecialchars($f['type'], ENT_QUOTES); ?>"
              data-flash-message="<?php echo htmlspecialchars($f['message'], ENT_QUOTES); ?>"
              hidden></span>
    <?php endforeach; ?>

    <main class="page-shell">
        <div class="container dashboard-layout">
            <aside class="sidebar">
                <div class="brand" style="margin-bottom: 20px;">
                    <div class="brand-mark"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></strong><br>
                        <small style="color: var(--muted);"><?php echo htmlspecialchars($user['account_role'] ?? ''); ?></small>
                    </div>
                </div>
                <div class="sidebar-nav">
                    <a href="index.php">Home</a>
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="events.php">Events</a>
                    <?php if (isLeader()): ?><a href="youths.php">Youth roster</a><?php endif; ?>
                    <a href="feedback.php">Suggestions</a>
                    <a href="change_password.php">Change password</a>
                </div>
            </aside>

            <div class="main-content">
                <section class="kpi-grid reveal">
                    <div class="kpi">
                        <span class="label">Youth members</span>
                        <div class="value" data-counter data-count="<?php echo (int) ($dashboard['youth_count'] ?? 0); ?>">0</div>
                        <span class="meta">Across churches</span>
                    </div>
                    <div class="kpi">
                        <span class="label">Leaders</span>
                        <div class="value" data-counter data-count="<?php echo (int) ($dashboard['leader_count'] ?? 0); ?>">0</div>
                        <span class="meta">Coordinating ministry</span>
                    </div>
                    <div class="kpi">
                        <span class="label">Events</span>
                        <div class="value" data-counter data-count="<?php echo (int) ($dashboard['event_count'] ?? 0); ?>">0</div>
                        <span class="meta">Current schedule</span>
                    </div>
                    <div class="kpi">
                        <span class="label">Attendance rate</span>
                        <div class="value" data-counter data-count="<?php echo (float) ($dashboard['attendance_rate'] ?? 0); ?>">0%</div>
                        <div class="progress"><span data-progress="<?php echo (float) ($dashboard['attendance_rate'] ?? 0); ?>"></span></div>
                    </div>
                </section>

                <section class="subgrid">
                    <div class="dashboard-card reveal">
                        <h3>Quick status</h3>
                        <div class="notice">
                            Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>. Your church is
                            <strong><?php echo htmlspecialchars($user['church_name'] ?? ''); ?></strong>
                            and your role is <strong><?php echo htmlspecialchars($user['role_name'] ?? ''); ?></strong>.
                        </div>
                        <p>Platform health is strong. Attendance is trending and every youth event can be refined using the integrated feedback analysis.</p>
                    </div>

                    <div class="dashboard-card ai-box reveal">
                        <h3>AI suggestions</h3>
                        <p style="color: var(--muted); margin-bottom: 4px;">Average rating: <strong><?php echo (float) ($ai['average_rating'] ?? 0); ?>/5</strong></p>
                        <p style="color: var(--muted);">Attendance trend: <strong><?php echo (float) ($ai['attendance_rate'] ?? 0); ?>%</strong></p>
                        <ul>
                            <?php if (!empty($ai['suggestions'])): ?>
                                <?php foreach ($ai['suggestions'] as $tip): ?>
                                    <li><?php echo htmlspecialchars($tip); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No suggestions available yet.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </section>

                <?php if (isAdmin()): ?>
                    <section class="dashboard-card reveal">
                        <h3>Admin summary</h3>
                        <div class="kpi-grid">
                            <div class="kpi">
                                <span class="label">Pending leaders</span>
                                <div class="value" data-counter data-count="<?php echo (int) ($adminSummary['pending_leaders'] ?? 0); ?>">0</div>
                                <span class="meta">Leader approvals</span>
                            </div>
                            <div class="kpi">
                                <span class="label">Pending youth</span>
                                <div class="value" data-counter data-count="<?php echo (int) ($adminSummary['pending_youths'] ?? 0); ?>">0</div>
                                <span class="meta">Youth registrations</span>
                            </div>
                            <div class="kpi">
                                <span class="label">Upcoming</span>
                                <div class="value" data-counter data-count="<?php echo (int) ($adminSummary['upcoming_events'] ?? 0); ?>">0</div>
                                <span class="meta">Scheduled</span>
                            </div>
                            <div class="kpi">
                                <span class="label">Ongoing</span>
                                <div class="value" data-counter data-count="<?php echo (int) ($adminSummary['ongoing_events'] ?? 0); ?>">0</div>
                                <span class="meta">Live events</span>
                            </div>
                            <div class="kpi">
                                <span class="label">Completed</span>
                                <div class="value" data-counter data-count="<?php echo (int) ($adminSummary['completed_events'] ?? 0); ?>">0</div>
                                <span class="meta">Finished</span>
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-card card-section reveal">
                        <h3>✏️ Edit event details</h3>
                        <p style="color: var(--muted); margin-bottom: 16px;">Update an event's title, schedule, venue, or target attendance.</p>

                        <?php if (!empty($events)): ?>
                            <div class="approval-list">
                                <?php foreach ($events as $event): ?>
                                    <div class="pending-item" style="display: block;">
                                        <form method="post" action="dashboard.php" style="display: grid; gap: 12px; width: 100%;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="form_type" value="update_event">
                                            <input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">

                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                                                <div class="field">
                                                    <label>Title</label>
                                                    <input type="text" name="title" value="<?php echo htmlspecialchars((string) ($event['title'] ?? '')); ?>" required>
                                                </div>
                                                <div class="field">
                                                    <label>Venue</label>
                                                    <input type="text" name="venue" value="<?php echo htmlspecialchars((string) ($event['venue'] ?? '')); ?>" required>
                                                </div>
                                                <div class="field">
                                                    <label>Start date</label>
                                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars((string) ($event['start_date'] ?: $event['event_date'] ?? '')); ?>" required>
                                                </div>
                                                <div class="field">
                                                    <label>End date</label>
                                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars((string) ($event['end_date'] ?: $event['start_date'] ?: $event['event_date'] ?? '')); ?>">
                                                </div>
                                                <div class="field">
                                                    <label>Status</label>
                                                    <select name="status">
                                                        <?php $es = strtolower((string) ($event['status'] ?? 'upcoming')); ?>
                                                        <option value="upcoming" <?php echo $es === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                        <option value="ongoing"  <?php echo $es === 'ongoing'  ? 'selected' : ''; ?>>Ongoing</option>
                                                        <option value="completed"<?php echo $es === 'completed'? 'selected' : ''; ?>>Completed</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label>Target attendees</label>
                                                    <input type="number" name="target_attendees" min="0" value="<?php echo (int) ($event['target_attendees'] ?? 0); ?>">
                                                </div>
                                                                                            <div class="field">
                                                <label>External link (optional)</label>
                                                <input type="url" name="link_url" value="<?php echo htmlspecialchars((string) ($event['link_url'] ?? '')); ?>" placeholder="https://...">
                                            </div>
                                            <div class="field">
                                                <label>Link button label</label>
                                                <input type="text" name="link_label" value="<?php echo htmlspecialchars((string) ($event['link_label'] ?? '')); ?>" placeholder="Register here, Join WhatsApp...">
                                            </div>
                                            </div>

                                            <div class="field">
                                                <label>Description</label>
                                                <textarea name="description" rows="3"><?php echo htmlspecialchars((string) ($event['description'] ?? '')); ?></textarea>
                                            </div>

                                            <div style="display: flex; justify-content: flex-end;">
                                                <button class="button primary" type="submit">Save changes</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="notice">No events available yet.</div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-card card-section reveal">
                        <h3>🔔 Pending leader approvals</h3>
                        <p style="color: var(--muted); margin-bottom: 16px;">Approve youth leader accounts before they can access the leadership dashboard.</p>

                        <?php if (!empty($pendingLeaders)): ?>
                            <div class="approval-list">
                                <?php foreach ($pendingLeaders as $leader): ?>
                                    <div class="pending-item">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($leader['name'] ?? ''); ?></strong><br>
                                            <small>📧 <?php echo htmlspecialchars($leader['email'] ?? ''); ?></small><br>
                                            <small>🏢 <?php echo htmlspecialchars($leader['church_name'] ?? ''); ?> • <?php echo htmlspecialchars($leader['role_name'] ?? ''); ?></small>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <form method="post" action="dashboard.php">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="approve_leader">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $leader['id']; ?>">
                                                <button class="button primary" type="submit">✓ Approve</button>
                                            </form>
                                            <form method="post" action="dashboard.php">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="reject_leader">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $leader['id']; ?>">
                                                <button class="button danger" type="submit" data-confirm="Reject this leader registration?">✕ Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="notice success">✓ All leader registrations have been reviewed.</div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (isLeader()): ?>
                    <section class="dashboard-card card-section reveal">
                        <h3>🔔 Pending youth approvals</h3>
                        <p style="color: var(--muted); margin-bottom: 16px;">Review and approve new youth registrations from different churches.</p>

                        <?php if (!empty($pendingYouths)): ?>
                            <div class="approval-list">
                                <?php foreach ($pendingYouths as $youth): ?>
                                    <div class="pending-item">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($youth['name'] ?? ''); ?></strong><br>
                                            <small>📧 <?php echo htmlspecialchars($youth['email'] ?? ''); ?></small><br>
                                            <small>🏢 <?php echo htmlspecialchars($youth['church_name'] ?? ''); ?> • <?php echo htmlspecialchars($youth['role_name'] ?? ''); ?></small>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <form method="post" action="dashboard.php">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="approve_youth">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $youth['id']; ?>">
                                                <button class="button primary" type="submit">✓ Approve</button>
                                            </form>
                                            <form method="post" action="dashboard.php">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="reject_youth">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $youth['id']; ?>">
                                                <button class="button danger" type="submit" data-confirm="Reject this youth registration?">✕ Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="notice success">✓ All youth registrations have been reviewed and approved!</div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-card card-section reveal">
                        <h3>📊 Church membership overview</h3>
                        <p style="color: var(--muted); margin-bottom: 16px;">Youth distribution across <?php echo (int) $totalChurches; ?> church<?php echo (int) $totalChurches !== 1 ? 'es' : ''; ?>.</p>

                        <?php if (!empty($churchYouthCounts)): ?>
                            <div style="display: grid; gap: 12px;">
                                <?php foreach ($churchYouthCounts as $church): ?>
                                    <div class="pending-item" style="justify-content: space-between;">
                                        <strong><?php echo htmlspecialchars($church['church_name'] ?? ''); ?></strong>
                                        <div style="background: rgba(94, 198, 255, 0.2); padding: 8px 12px; border-radius: 8px; font-weight: 700; color: #5ec6ff;">
                                            <?php echo (int) ($church['youth_count'] ?? 0); ?> youth
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No youth members registered yet.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                    <section class="dashboard-card card-section reveal">
                        <h3>👥 User management</h3>
                        <p style="color: var(--muted); margin-bottom: 16px;">View all registered users and remove accounts when necessary.</p>

                        <div class="table-toolbar" data-table-tools="#users-table">
                            <div class="table-search">
                                <input type="search" placeholder="Search by name, email, or role..." data-table-search>
                            </div>
                            <div class="filter-chips">
                                <button class="chip is-active" data-filter="all">All</button>
                                <button class="chip" data-filter="youth">Youth</button>
                                <button class="chip" data-filter="leader">Leaders</button>
                                <button class="chip" data-filter="pending">Pending</button>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table id="users-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($youths as $account): ?>
                                        <?php
                                            $acctRole = strtolower((string) ($account['account_role'] ?? ''));
                                            $approved = (int) ($account['is_approved'] ?? 0) === 1;
                                            $filterStatus = $approved ? $acctRole : 'pending';
                                        ?>
                                        <tr data-status="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES); ?>">
                                            <td><?php echo htmlspecialchars($account['name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($account['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($account['account_role'] ?? ''); ?></td>
                                            <td>
                                                <?php if ($approved): ?>
                                                    <span class="badge success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int) ($account['id'] ?? 0) !== (int) ($user['id'] ?? 0)): ?>
                                                    <form method="post" action="dashboard.php" style="display:inline;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="form_type" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) ($account['id'] ?? 0); ?>">
                                                        <button class="button danger" type="submit" data-confirm="Delete this user account and all associated records?">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color: var(--muted);">Current</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="form-card reveal">
                    <h3>Add a photo to the gallery</h3>
                    <p style="color: var(--muted); margin-bottom: 16px;">Share moments from events, meetings, or ministry activities. Your photos will appear in the public gallery.</p>
                    <form method="post" action="dashboard.php" enctype="multipart/form-data" data-loading>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="form_type" value="upload_gallery">
                        <div class="form-grid">
                            <div class="field full">
                                <label for="gallery_photo">Photo</label>
                                <input id="gallery_photo" name="gallery_photo" type="file" accept="image/*" required>
                            </div>
                            <div class="field full">
                                <label for="gallery_caption">Caption</label>
                                <input id="gallery_caption" name="caption" type="text" placeholder="Add a caption for this photo (optional)">
                            </div>
                        </div>
                        <div class="action-row" style="margin-top: 20px;">
                            <button class="button primary" type="submit">Upload photo</button>
                        </div>
                    </form>
                </section>

                <?php if (isLeader()): ?>
                    <section class="form-card reveal">
                        <h3>Create a new youth event</h3>
                        <form method="post" action="dashboard.php" data-loading>
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="form_type" value="create_event">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="event_title">Event title</label>
                                    <input id="event_title" type="text" name="title" required>
                                </div>
                                <div class="field">
                                    <label for="event_start_date">Start date</label>
                                    <input id="event_start_date" type="date" name="start_date" required>
                                </div>
                                <div class="field">
                                    <label for="event_end_date">End date</label>
                                    <input id="event_end_date" type="date" name="end_date" required>
                                </div>
                                <div class="field">
                                    <label for="event_venue">Venue</label>
                                    <input id="event_venue" type="text" name="venue" required>
                                </div>
                                <div class="field">
                                    <label for="event_target_attendees">Target attendees</label>
                                    <input id="event_target_attendees" type="number" name="target_attendees" min="0" value="50">
                                </div>
                                <div class="field">
                                    <label for="event_status">Status</label>
                                    <select id="event_status" name="status">
                                        <option value="upcoming">Upcoming</option>
                                        <option value="ongoing">Ongoing</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="event_description">Description</label>
                                    <textarea id="event_description" name="description" placeholder="Describe the purpose and expected outcomes of the event."></textarea>
                                </div>
                                                                <div class="field">
                                    <label for="event_link_url">External link (optional)</label>
                                    <input id="event_link_url" type="url" name="link_url" placeholder="https://forms.gle/... or https://meet.google.com/...">
                                </div>
                                <div class="field">
                                    <label for="event_link_label">Link button label</label>
                                    <input id="event_link_label" type="text" name="link_label" placeholder="Register here, Join WhatsApp, Watch live...">
                                </div>
                            </div>
                            <div class="action-row" style="margin-top: 20px;">
                                <button class="button primary" type="submit">Save event</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="table-card reveal">
                    <h3>Upcoming events</h3>

                    <div class="table-toolbar" data-table-tools="#events-table">
                        <div class="table-search">
                            <input type="search" placeholder="Search events..." data-table-search>
                        </div>
                        <div class="filter-chips">
                            <button class="chip is-active" data-filter="all">All</button>
                            <button class="chip" data-filter="upcoming">Upcoming</button>
                            <button class="chip" data-filter="ongoing">Ongoing</button>
                            <button class="chip" data-filter="completed">Completed</button>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="events-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                    <th>Target</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <?php
                                        $eid = (int) $event['id'];
                                        $s = $eventStats[$eid] ?? ['attendance_rate' => 0, 'total' => 0];
                                        $statusKey = strtolower((string) $event['status']);
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES); ?>">
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td><?php echo htmlspecialchars(formatDate($event['start_date'] ?: $event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td><span class="badge" data-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES); ?>"><?php echo htmlspecialchars(ucfirst((string) $event['status'])); ?></span></td>
                                        <td><?php echo (int) $event['target_attendees']; ?></td>
                                        <td style="min-width: 160px;">
                                            <div class="progress"><span data-progress="<?php echo (float) $s['attendance_rate']; ?>"></span></div>
                                            <small style="color: var(--muted);"><?php echo (float) $s['attendance_rate']; ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="subgrid">
                    <div class="form-card reveal">
                        <h3>Register for an event</h3>
                        <form method="post" action="dashboard.php" data-loading>
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="form_type" value="register_event">
                            <div class="form-grid">
                                <div class="field full">
                                    <label for="register_event_id">Event</label>
                                    <select id="register_event_id" name="event_id" required>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo (int) $event['id']; ?>" data-status="<?php echo htmlspecialchars($event['status']); ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="register_attendance_status">Attendance status</label>
                                    <select id="register_attendance_status" name="attendance_status">
                                        <option value="registered">Registered</option>
                                        <option value="present">Present</option>
                                        <option value="late">Late</option>
                                        <option value="absent">Absent</option>
                                        <option value="dayschooller">Dayschooller</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="register_notes">Notes</label>
                                    <textarea id="register_notes" name="notes" placeholder="Share quick notes for your attendance or team role."></textarea>
                                </div>
                            </div>
                            <div class="action-row" style="margin-top: 20px;">
                                <button class="button primary" type="submit">Submit</button>
                            </div>
                        </form>
                        <script>
                            (() => {
                                const eventSelect = document.getElementById('register_event_id');
                                const attendanceSelect = document.getElementById('register_attendance_status');
                                if (!eventSelect || !attendanceSelect) return;
                                const map = {};
                                Array.from(eventSelect.options).forEach(o => { map[o.value] = o.dataset.status || 'upcoming'; });
                                function sync() {
                                    const status = map[eventSelect.value] || 'upcoming';
                                    const options = status === 'ongoing'
                                        ? [['present','Present'],['absent','Absent'],['late','Late'],['dayschooller','Dayschooller']]
                                        : [['registered','Registered']];
                                    attendanceSelect.innerHTML = options.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
                                }
                                eventSelect.addEventListener('change', sync);
                                sync();
                            })();
                        </script>
                    </div>

                    <div class="form-card reveal">
                        <h3>Share feedback</h3>
                        <form method="post" action="dashboard.php" data-loading>
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="form_type" value="submit_feedback">
                            <div class="form-grid">
                                <div class="field full">
                                    <label for="feedback_event_id">Event</label>
                                    <select id="feedback_event_id" name="event_id" required>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo (int) $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="feedback_rating">Rating</label>
                                    <select id="feedback_rating" name="rating">
                                        <option value="5">5 - Excellent</option>
                                        <option value="4">4 - Good</option>
                                        <option value="3">3 - Average</option>
                                        <option value="2">2 - Fair</option>
                                        <option value="1">1 - Poor</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="feedback_mood">Mood</label>
                                    <select id="feedback_mood" name="mood">
                                        <option value="positive">Positive</option>
                                        <option value="neutral">Neutral</option>
                                        <option value="concerned">Concerned</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="feedback_comment">Comment</label>
                                    <textarea id="feedback_comment" name="comment" required placeholder="What stood out during the event?"></textarea>
                                </div>
                                <div class="field full">
                                    <label for="feedback_suggestion">Suggestion</label>
                                    <textarea id="feedback_suggestion" name="suggestion" required placeholder="How can we improve this event next time?"></textarea>
                                </div>
                            </div>
                            <div class="action-row" style="margin-top: 20px;">
                                <button class="button primary" type="submit">Send feedback</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <h3>CYA Connect</h3>
                <p>Empowering youth within Kabartegan Region churches.</p>
            </div>
            <div class="footer-support">
                <a href="tel:+254729378556" aria-label="Help line">📞</a>
                <a href="mailto:hello@cyaconnect.org" aria-label="Email support">✉️</a>
                <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook">f</a>
                <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">◎</a>
                <a href="https://www.tiktok.com" target="_blank" rel="noopener noreferrer" aria-label="TikTok">♪</a>
                <a href="https://wa.me/254700000000" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">✆</a>
            </div>
        </div>
        <div class="container footer-meta">© <span data-year></span> CYA Connect.</div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>