<?php
require_once __DIR__ . '/config.php';
requireLogin();

if (!isLeader()) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    validateCsrf();

    if ($_POST['form_type'] === 'approve_youth') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = db()->prepare('UPDATE users SET is_approved = 1 WHERE id = :id AND account_role = :role');
            $stmt->execute([':id' => $userId, ':role' => 'youth']);
            flash('Youth approved.', 'success');
        }
        redirect('youths.php');
    }
}

$users = listYouthUsers();
$pendingYouths = listPendingYouths();
$churchYouthCounts = listChurchYouthCounts();
$totalChurches = getChurchCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Youth Roster | CYA Connect</title>
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
                <a href="dashboard.php">Dashboard</a>
                <a href="events.php">Events</a>
                <a href="youths.php" class="active">Youths</a>
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
                <p class="eyebrow">Youth roster</p>
                <h2>Monitor all church youths and assigned roles</h2>
            </div>

            <section class="dashboard-card card-section reveal">
                <h3>Pending approvals</h3>
                <div class="kpi-grid">
                    <div class="kpi">
                        <span class="label">Awaiting review</span>
                        <div class="value" data-counter data-count="<?php echo count($pendingYouths); ?>">0</div>
                        <span class="meta">Youth registrations</span>
                    </div>
                </div>

                <?php if (!empty($pendingYouths)): ?>
                    <div class="approval-list">
                        <?php foreach ($pendingYouths as $pendingYouth): ?>
                            <div class="pending-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($pendingYouth['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($pendingYouth['church_name']); ?></small>
                                </div>
                                <form method="post" action="youths.php">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="form_type" value="approve_youth">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $pendingYouth['id']; ?>">
                                    <button class="button primary" type="submit">Approve</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">There are no pending youth registrations right now.</p>
                <?php endif; ?>
            </section>

            <section class="dashboard-card card-section reveal">
                <h3>Church membership</h3>
                <div class="kpi-grid">
                    <div class="kpi">
                        <span class="label">Total churches</span>
                        <div class="value" data-counter data-count="<?php echo (int) $totalChurches; ?>">0</div>
                        <span class="meta">Represented</span>
                    </div>
                    <?php foreach ($churchYouthCounts as $church): ?>
                        <div class="kpi">
                            <span class="label"><?php echo htmlspecialchars($church['church_name']); ?></span>
                            <div class="value" data-counter data-count="<?php echo (int) $church['youth_count']; ?>">0</div>
                            <span class="meta">Youth members</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="table-card reveal">
                <div class="table-toolbar" data-table-tools="#youth-table">
                    <div class="table-search">
                        <input type="search" placeholder="Search by name, church, or role..." data-table-search>
                    </div>
                    <div class="filter-chips">
                        <button class="chip is-active" data-filter="all">All</button>
                        <button class="chip" data-filter="approved">Approved</button>
                        <button class="chip" data-filter="pending">Pending</button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="youth-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Church</th>
                                <th>Assigned role</th>
                                <th>Account</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php
                                    $approved = (int) ($u['is_approved'] ?? 0) === 1;
                                    $filterStatus = $approved ? 'approved' : 'pending';
                                ?>
                                <tr data-status="<?php echo $filterStatus; ?>">
                                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($u['phone_number'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($u['church_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                                    <td><span class="badge <?php echo $u['account_role'] === 'leader' ? 'warning' : 'success'; ?>"><?php echo htmlspecialchars($u['account_role']); ?></span></td>
                                    <td>
                                        <?php if ($approved): ?>
                                            <span class="badge success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$approved && $u['account_role'] === 'youth'): ?>
                                            <form method="post" action="youths.php" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="approve_youth">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                <button class="button primary" type="submit">Approve</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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