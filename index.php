<?php
require_once __DIR__ . '/config.php';

$user = currentUser();
$stats = getDashboardData();
$galleryPhotos = listGalleryPhotos();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | CYA Connect</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <div class="container nav">
            <a href="index.php" class="brand" style="text-decoration:none;">
                <div class="brand-mark">C</div>
                <span>AIC KABARTEGAN RCC YOUTH CONNECT</span>
            </a>
            <button class="nav-toggle" type="button" data-toggle-menu aria-expanded="false" aria-label="Toggle menu"><span></span></button>
            <nav class="nav-links" data-nav-menu>
                <a href="index.php#features">Features</a>
                <a href="index.php#overview">Overview</a>
                <a href="index.php#about">About</a>
                <a href="index.php#gallery">Gallery</a>
                <a href="login.php">Login</a>
                <a href="register.php" class="button primary">Register</a>
            </nav>
        </div>
    </header>

    <section class="page-banner" aria-label="Youth ministry banner">
        <img src="https://images.unsplash.com/photo-1507692049790-de58290a4334?auto=format&fit=crop&w=1800&q=80" alt="Live praise and worship">
        <div class="page-banner-overlay">
            <span>OUR REGION OUR MANDATE</span>
        </div>
    </section>

    <section class="theme-verse" aria-label="Theme verse banner">
        <div class="theme-verse-track">
            <span>Acts 16:25-35 &nbsp;|&nbsp; "About midnight Paul and Silas were praying and singing hymns to God, and the other prisoners were listening to them. Suddenly there was such a violent earthquake that the foundations of the prison were shaken. At once all the prison doors flew open, and everyone's chains came loose. The jailer woke up, and when he saw the prison doors open, he drew his sword and was about to kill himself because he thought the prisoners had escaped. But Paul shouted, 'Don't harm yourself! We are all here!' The jailer called for lights, rushed in and fell trembling before Paul and Silas. He then brought them out and asked, 'Sirs, what must I do to be saved?' They replied, 'Believe in the Lord Jesus, and you will be saved—you and your household.' Then they spoke the word of the Lord to him and to all the others in his house. At that hour of the night the jailer took them and washed their wounds; then immediately he and all his household were baptized. The jailer brought them into his house and set a meal before them; he was filled with joy because he had come to believe in God—he and his whole household. When it was daylight, the magistrates sent their officers to the jailer with the order: 'Release those men.'"</span>
            <span>Acts 16:25-35 &nbsp;|&nbsp; "About midnight Paul and Silas were praying and singing hymns to God, and the other prisoners were listening to them. Suddenly there was such a violent earthquake that the foundations of the prison were shaken. At once all the prison doors flew open, and everyone's chains came loose. The jailer woke up, and when he saw the prison doors open, he drew his sword and was about to kill himself because he thought the prisoners had escaped. But Paul shouted, 'Don't harm yourself! We are all here!' The jailer called for lights, rushed in and fell trembling before Paul and Silas. He then brought them out and asked, 'Sirs, what must I do to be saved?' They replied, 'Believe in the Lord Jesus, and you will be saved—you and your household.' Then they spoke the word of the Lord to him and to all the others in his house. At that hour of the night the jailer took them and washed their wounds; then immediately he and all his household were baptized. The jailer brought them into his house and set a meal before them; he was filled with joy because he had come to believe in God—he and his whole household. When it was daylight, the magistrates sent their officers to the jailer with the order: 'Release those men.'"</span>
        </div>
    </section>

    <main>
        <section class="hero">
            <div class="container hero-shell">
                <div class="reveal">
                    <p class="eyebrow">AIC KABARTEGAN YOUTH CONNECT FOR JESUS</p>
                    <h1>Empowering Young People. Building Faith. Inspiring Purpose.</h1>
                    <p class="hero-copy">
                        KBN Youth Connect helps church youth leaders manage events, monitor attendance, assign roles, track engagement, collect feedback and empower our young people as they grow in faith, fellowship &amp; service across different churches in one smart digital platform.
                    </p>
                    <div class="action-row">
                        <a href="register.php" class="button primary">Create account</a>
                        <a href="login.php" class="button secondary">Sign in</a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-box">
                            <div class="stat-number" data-counter data-count="<?php echo (int) $stats['youth_count']; ?>">0</div>
                            <div class="stat-label">Registered youths</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" data-counter data-count="<?php echo (int) $stats['event_count']; ?>">0</div>
                            <div class="stat-label">Events planned</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" data-counter data-count="<?php echo (float) $stats['attendance_rate']; ?>">0%</div>
                            <div class="stat-label">Attendance rate</div>
                        </div>
                    </div>
                </div>

                <div class="hero-panel reveal">
                    <div class="panel-card">
                        <p class="eyebrow">Smart overview</p>
                        <h3>The current generation youth ministry</h3>
                        <div class="mini-grid">
                            <div class="mini-card">
                                <strong>Role-based access</strong>
                                <p>Youths and youth leaders each work in their assigned spaces.</p>
                            </div>
                            <div class="mini-card">
                                <strong>AI suggestions</strong>
                                <p>Analyze feedback and attendance to improve every event.</p>
                            </div>
                            <div class="mini-card">
                                <strong>Cross-church network</strong>
                                <p>Celebrate every church while connecting youth teams.</p>
                            </div>
                            <div class="mini-card">
                                <strong>Feedback loop</strong>
                                <p>Capture ideas, comments, and suggestions instantly.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="section">
            <div class="container">
                <div class="section-title reveal">
                    <p class="eyebrow">Why this platform matters</p>
                    <h2>Faith. Fellowship. Leadership. Service</h2>
                </div>
                <div class="grid-3">
                    <article class="feature-card reveal">
                        <div class="feature-icon">📅</div>
                        <h3>Youth programs</h3>
                        <p>Schedule church youth events, assign leaders, track attendance, and monitor participation in real time.</p>
                    </article>
                    <article class="feature-card reveal">
                        <div class="feature-icon">👥</div>
                        <h3>Youth monitoring</h3>
                        <p>Keep a clear record of every youth, their church, and their team role for better supervision and engagement.</p>
                    </article>
                    <article class="feature-card reveal">
                        <div class="feature-icon">📖</div>
                        <h3>Bible Study</h3>
                        <p>Grow deeper in God's word through engaging Bible studies and discussions.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="overview" class="section">
            <div class="container">
                <div class="section-title reveal">
                    <p class="eyebrow">Operational view</p>
                    <h2>Everything youth leaders need in one place</h2>
                </div>
                <div class="grid-3">
                    <div class="feature-card reveal">
                        <h3>Registration</h3>
                        <p>Each youth registers with their church, ministry role, and assigned responsibility so the system remains organized.</p>
                    </div>
                    <div class="feature-card reveal">
                        <h3>Feedback</h3>
                        <p>Youths can share comments and suggestions to improve worship programs, outreach events, and youth activities.</p>
                    </div>
                    <div class="feature-card reveal">
                        <h3>Performance</h3>
                        <p>Monitor attendance and engagement trends to create consistent, high-impact youth ministry experiences.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="section about-section">
            <div class="container about-shell reveal">
                <div class="about-copy">
                    <p class="eyebrow">About us</p>
                    <h2>Helping church youth grow in faith, unity, and leadership.</h2>
                    <p>
                        AIC Kabartegan RCC Youth Connect is a digital space created to unite youth across churches, strengthen communication,
                        and make ministry leadership more organized and impactful. The platform helps leaders coordinate activities, track participation,
                        and support youth development through better planning and timely feedback.
                    </p>
                    <p>
                        Our mission is to encourage active discipleship, collective responsibility, and meaningful engagement among youth in our churches.
                    </p>
                </div>
                <div class="about-card">
                    <div class="about-item">
                        <strong>Mission</strong>
                        <span>Build a connected youth community rooted in faith and service.</span>
                    </div>
                    <div class="about-item">
                        <strong>Vision</strong>
                        <span>Develop future leaders who are active, disciplined, and united in purpose.</span>
                    </div>
                    <div class="about-item">
                        <strong>Impact</strong>
                        <span>Improve attendance, coordination, and youth involvement across the region.</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="gallery" class="section">
            <div class="container">
                <div class="section-title reveal">
                    <p class="eyebrow">Gallery</p>
                    <h2>Moments shared by our youth and leaders</h2>
                </div>

                <?php if (empty($galleryPhotos)): ?>
                    <div class="notice">
                        No photos have been uploaded to the gallery yet.
                        <?php if ($user): ?>You can share your photos from the dashboard.<?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="gallery-slider reveal" aria-label="Youth and leader photo gallery">
                        <div class="gallery-track">
                            <?php foreach (array_merge($galleryPhotos, $galleryPhotos) as $photo): ?>
                                <figure class="gallery-item">
                                    <img src="<?php echo htmlspecialchars($photo['image_path']); ?>"
                                         alt="<?php echo htmlspecialchars($photo['caption'] ?: $photo['name']); ?>"
                                         loading="lazy">
                                    <figcaption>
                                        <?php echo htmlspecialchars($photo['name']); ?> • <?php echo htmlspecialchars($photo['church_name']); ?>
                                    </figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
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