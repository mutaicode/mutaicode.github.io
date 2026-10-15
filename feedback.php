<?php
require_once __DIR__ . '/config.php';
requireLogin();

if (!isLeader()) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && isLeader()) {
    validateCsrf();

    if ($_POST['form_type'] === 'delete_feedback') {
        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        if ($feedbackId > 0) {
            db()->prepare('DELETE FROM feedback WHERE id = :id')->execute([':id' => $feedbackId]);
            flash('Feedback removed.', 'info');
        }
        redirect('feedback.php');
    }
}

$feedback = listFeedback();
$ai = getAiSuggestions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | CYA Connect</title>
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
                <?php if (isLeader()): ?><a href="youths.php">Youths</a><?php endif; ?>
                <a href="feedback.php" class="active">Feedback</a>
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
                <p class="eyebrow">Feedback loop</p>
                <h2>Youth opinions and AI-driven suggestions</h2>
            </div>

            <section class="dashboard-card ai-box reveal" style="margin-bottom: 24px;">
                <h3>AI insight summary</h3>
                <p><strong>Average rating:</strong> <?php echo (float) ($ai['average_rating'] ?? 0); ?> / 5</p>
                <p><strong>Participation rate:</strong> <?php echo (float) ($ai['attendance_rate'] ?? 0); ?>%</p>
                <ul>
                    <?php foreach ($ai['suggestions'] as $tip): ?>
                        <li><?php echo htmlspecialchars($tip); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <div class="table-card reveal">
                <div class="table-toolbar" data-table-tools="#feedback-table">
                    <div class="table-search">
                        <input type="search" placeholder="Search feedback..." data-table-search>
                    </div>
                </div>

                <?php if (empty($feedback)): ?>
                    <p class="no-data">No feedback submitted yet.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table id="feedback-table">
                            <thead>
                                <tr>
                                    <th>Youth</th>
                                    <th>Event</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Suggestion</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedback as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['youth_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                        <td><?php echo (int) $row['rating']; ?> / 5</td>
                                        <td><?php echo htmlspecialchars($row['comment']); ?></td>
                                        <td><?php echo htmlspecialchars($row['suggestion']); ?></td>
                                        <td>
                                            <form method="post" action="feedback.php" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="form_type" value="delete_feedback">
                                                <input type="hidden" name="feedback_id" value="<?php echo (int) $row['id']; ?>">
                                                <button class="button danger" type="submit" data-confirm="Delete this suggestion?">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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