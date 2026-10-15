<?php
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && isLeader()) {
    validateCsrf();

    if ($_POST['form_type'] === 'delete_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0 && eventExists($eventId)) {
            $stmt = db()->prepare('SELECT status FROM events WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $eventId]);
            if (strtolower((string) $stmt->fetchColumn()) === 'completed') {
                db()->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $eventId]);
                flash('Completed event deleted.', 'info');
            } else {
                flash('Only completed events can be deleted.', 'warning');
            }
        }
        redirect('events.php');
    }
}

$user = currentUser();
$events = listEvents();

$eventIds = array_map(fn($e) => (int) $e['id'], $events);
$statsByEvent = getEventStatsBulk($eventIds);
$attendanceByEvent = getAttendanceForEvents($eventIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | CYA Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <div class="container nav">
            <a href="index.php" class="brand" style="text-decoration:none;">
                <div class="brand-mark">VBS</div>
                <span>AIC KABARTEGAN YOUTH CONNECT</span>
            </a>
            <button class="nav-toggle" type="button" data-toggle-menu aria-expanded="false" aria-label="Toggle menu"><span></span></button>
            <nav class="nav-links" data-nav-menu>
                <a href="dashboard.php">Dashboard</a>
                <a href="events.php" class="active">Events</a>
                <?php if (isLeader()): ?><a href="youths.php">Youths</a><?php endif; ?>
                <a href="feedback.php">Feedback</a>
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
        <div class="container">
            <div class="section-title reveal">
                <p class="eyebrow">Event monitoring</p>
                <h2>Youth event attendance and planning</h2>
            </div>

            <?php if (empty($events)): ?>
                <div class="notice">No events have been created yet.</div>
            <?php endif; ?>

            <?php foreach ($events as $event): ?>
                <?php
                    $eid = (int) $event['id'];
                    $stats = $statsByEvent[$eid] ?? ['attendance_rate' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'registered' => 0, 'total' => 0];
                    $statusKey = strtolower((string) $event['status']);
                    $rows = $attendanceByEvent[$eid] ?? [];
                ?>
                <div class="table-card card-section reveal">
                                    <div class="event-header">
                    <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span class="badge" data-status="<?php echo htmlspecialchars($statusKey, ENT_QUOTES); ?>"><?php echo htmlspecialchars(ucfirst((string) $event['status'])); ?></span>
                        <?php $eventLink = safeEventLink($event['link_url'] ?? ''); ?>
                        <?php if ($eventLink): ?>
                            <a class="button primary event-link-btn"
                               href="<?php echo htmlspecialchars($eventLink, ENT_QUOTES); ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                🔗 <?php echo htmlspecialchars(eventLinkLabel($event['link_label'] ?? '', $eventLink)); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                    <p class="event-description"><?php echo htmlspecialchars($event['description'] ?: 'No description available.'); ?></p>

                    <div class="kpi-grid event-stats">
                        <div class="kpi">
                            <span class="label">Start date</span>
                            <div class="value date-value"><?php echo htmlspecialchars(formatDate($event['start_date'] ?: $event['event_date'])); ?></div>
                        </div>
                        <div class="kpi">
                            <span class="label">End date</span>
                            <div class="value date-value"><?php echo htmlspecialchars(formatDate($event['end_date'] ?: $event['start_date'] ?: $event['event_date'])); ?></div>
                        </div>
                        <div class="kpi">
                            <span class="label">Venue</span>
                            <div class="value date-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                        </div>
                        <div class="kpi">
                            <span class="label">Attendance</span>
                            <div class="value stat-value-large" data-counter data-count="<?php echo (float) $stats['attendance_rate']; ?>">0%</div>
                            <div class="progress"><span data-progress="<?php echo (float) $stats['attendance_rate']; ?>"></span></div>
                        </div>
                        <div class="kpi">
                            <span class="label">Target</span>
                            <div class="value stat-value-large"><?php echo (int) $event['target_attendees']; ?></div>
                        </div>
                    </div>

                    <?php if (isLeader() && $statusKey === 'completed'): ?>
                        <form method="post" action="events.php" class="action-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="form_type" value="delete_event">
                            <input type="hidden" name="event_id" value="<?php echo $eid; ?>">
                            <button class="button danger" type="submit" data-confirm="Delete this completed event and its related records?">Delete completed event</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($rows)): ?>
                        <div class="table-toolbar" data-table-tools="#attendance-<?php echo $eid; ?>">
                            <div class="table-search">
                                <input type="search" placeholder="Search attendees..." data-table-search>
                            </div>
                            <div class="filter-chips">
                                <button class="chip is-active" data-filter="all">All</button>
                                <button class="chip" data-filter="present">Present</button>
                                <button class="chip" data-filter="late">Late</button>
                                <button class="chip" data-filter="absent">Absent</button>
                                <button class="chip" data-filter="registered">Registered</button>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table id="attendance-<?php echo $eid; ?>">
                                <thead>
                                    <tr>
                                        <th>Youth</th>
                                        <th>Church</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Note / Team Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr data-status="<?php echo htmlspecialchars(strtolower((string) $row['status']), ENT_QUOTES); ?>">
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['church_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['role_name']); ?></td>
                                            <td><span class="badge" data-status="<?php echo htmlspecialchars(strtolower((string) $row['status']), ENT_QUOTES); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['notes'] ?: '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No attendance records for this event yet.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
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