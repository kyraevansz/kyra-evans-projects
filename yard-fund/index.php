<?php
require 'db.php';
require 'auth.php';

$logged_in = is_logged_in();
$user_type = current_user_type();

$approvedCount  = (int)   $pdo->query("SELECT COUNT(*) FROM claims_approved WHERE funding_status = 'open'")->fetchColumn();
$totalDonations = (float) $pdo->query("SELECT COALESCE(SUM(amount_sent), 0) FROM transactions")->fetchColumn();
$studentCount   = (int)   $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'student' AND is_active = 1")->fetchColumn();

// Top donors for the donor wall (non-anonymous, ordered by total given)
$topDonors = $pdo->query("
    SELECT u.full_name, u.profile_photo,
           SUM(t.amount_sent) AS total_given,
           COUNT(DISTINCT a.student_id) AS students_supported
    FROM transactions t
    JOIN users u ON t.donor_id = u.user_id
    JOIN claims_approved a ON t.claim_id = a.claim_id
    WHERE t.is_anonymous = 0 AND u.is_active = 1
    GROUP BY u.user_id, u.full_name, u.profile_photo
    ORDER BY total_given DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Yard Fund — Supporting VSU Students</title>
    <meta name="description" content="The Yard Fund connects VSU students in need with alumni and donors who want to give back.">
    <link rel="stylesheet" href="style.css">
</head>
<body class="landing-page">

<!-- ── Navbar ─────────────────────────────────────────── -->
<header class="site-header" id="top">
    <div class="header-inner">
        <a href="index.php" class="brand">
            <img src="uploads/yard-fund-logo.png" alt="The Yard Fund" class="brand-logo">
            <span class="brand-text">The Yard Fund</span>
        </a>

        <nav class="main-nav">
            <?php if ($logged_in): ?>
                <a href="donor_browse_claims.php">Browse Claims</a>
                <a href="#how-it-works">How It Works</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-trigger">
                        <?php
                        echo htmlspecialchars($_SESSION['full_name']);
                        ?>
                        <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-menu">
                        <?php if ($user_type === 'student'): ?>
                            <a href="student_dashboard.php">📊 My Dashboard</a>
                            <a href="student_submit_claim.php">📝 My Claim</a>
                            <a href="account_settings.php">⚙️ Account Settings</a>
                        <?php elseif ($user_type === 'donor'): ?>
                            <a href="donor_dashboard.php">📊 My Dashboard</a>
                            <a href="donor_browse_claims.php">🔍 Browse Claims</a>
                            <a href="account_settings.php">⚙️ Account Settings</a>
                        <?php elseif ($user_type === 'admin'): ?>
                            <a href="admin_dashboard.php">📊 Admin Dashboard</a>
                            <a href="admin_pending_claims.php">📋 Pending Claims</a>
                            <a href="admin_users.php">👥 Manage Users</a>
                            <a href="account_settings.php">⚙️ Account Settings</a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-logout">🚪 Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="#about">About Us</a>
                <a href="#mission">Our Mission</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#contact">Contact</a>
                <a href="donor_browse_claims.php">Browse Claims</a>
                <div class="nav-auth-buttons">
                    <a href="login.php" class="nav-login-link">Log In</a>
                    <a href="register.php" class="nav-login">Sign Up</a>
                </div>
            <?php endif; ?>
        </nav>

        <button class="nav-hamburger" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <?php if ($logged_in): ?>
            <a href="<?php echo dashboard_url(); ?>">My Dashboard</a>
            <a href="donor_browse_claims.php">Browse Claims</a>
            <a href="account_settings.php">Account Settings</a>
            <a href="logout.php">Log Out</a>
        <?php else: ?>
            <a href="#about">About Us</a>
            <a href="#mission">Our Mission</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#contact">Contact</a>
            <a href="donor_browse_claims.php">Browse Claims</a>
            <a href="login.php">Log In</a>
            <a href="register.php">Sign Up</a>
        <?php endif; ?>
    </div>
</header>

<main>

    <!-- ── Hero ──────────────────────────────────────────── -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <img src="uploads/yard-fund-logo.png" alt="The Yard Fund" class="hero-mascot">
            <div class="hero-text-block">
                <h1 class="hero-title">
                    <span class="hero-title-light">Plant a Seed,</span>
                    <span class="hero-title-accent">Grow a Future</span>
                </h1>
                <p class="hero-subtitle">
                    Built by VSU students, for VSU students. Freshman or senior, any major, any classification —
                    if you need support, The Yard Fund connects you with alumni and donors who want to give back.
                </p>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?php echo $approvedCount; ?></strong>
                    <span>Open Claims</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <strong>$<?php echo number_format($totalDonations, 0); ?></strong>
                    <span>Total Donated</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <strong><?php echo $studentCount; ?></strong>
                    <span>Students</span>
                </div>
            </div>
            <div class="hero-buttons">
                <?php if ($logged_in): ?>
                    <a class="hero-btn hero-btn-primary" href="<?php echo dashboard_url(); ?>">Go to My Dashboard</a>
                    <a class="hero-btn hero-btn-secondary" href="donor_browse_claims.php">Browse Claims</a>
                <?php else: ?>
                    <a class="hero-btn hero-btn-primary" href="register.php">Get Started</a>
                    <a class="hero-btn hero-btn-secondary" href="donor_browse_claims.php">Browse Claims</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ── About ─────────────────────────────────────────── -->
    <section id="about" class="about-section">
        <div class="section-container">
            <div class="about-layout">
                <div class="about-text">
                    <span class="section-label">About Us</span>
                    <h2>Born on <span>The Yard</span></h2>
                    <p>
                        The Yard Fund was founded in 2026 by six Virginia State University students who understood
                        the real challenges of funding a college education. Tuition needs change every semester —
                        and no student should have to stop their journey because of a balance they can't cover alone.
                    </p>
                    <p>
                        Sitting together on the yard one afternoon, we asked a simple question: <em>why is there no direct way
                        for VSU alumni and community donors to fund the students who need it most?</em> No middleman. No
                        bureaucracy. Just people who care, helping people who need it.
                    </p>
                    <p>
                        That conversation became The Yard Fund — open to every VSU student, regardless of major,
                        classification, or GPA. Freshman or senior, Engineering or Fine Arts, we built this
                        platform so the entire VSU community can find each other and make it happen.
                    </p>
                    <div class="about-founders">
                        <div class="founder-badge">🎓 Founded by VSU Students</div>
                        <div class="founder-badge">📍 Petersburg, Virginia</div>
                        <div class="founder-badge">🏛️ Est. 2026</div>
                    </div>
                </div>
                <div class="about-image-card">
                    <div class="about-image-icon">🏛️</div>
                    <h3>Virginia State University</h3>
                    <p style="color:#64748b;margin:4px 0 20px;">Petersburg, VA</p>
                    <div class="about-stat-list">
                        <div class="about-stat">
                            <strong>6</strong>
                            <span>Co-Founders</span>
                        </div>
                        <div class="about-stat">
                            <strong>1 Goal</strong>
                            <span>No student left behind</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Mission ───────────────────────────────────────── -->
    <section id="mission" class="mission-section">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label light">Our Mission</span>
                <h2>Every Student <span>Deserves a Chance</span></h2>
                <p>Financial barriers should never be the reason a talented student doesn't finish their degree. The Yard Fund exists to close that gap — one claim at a time.</p>
            </div>
            <div class="mission-grid">
                <div class="mission-card">
                    <div class="mission-icon">🤝</div>
                    <h3>Community First</h3>
                    <p>The VSU community — students, alumni, faculty, and friends — has the collective power to make sure no one gets left behind over a tuition balance.</p>
                </div>
                <div class="mission-card">
                    <div class="mission-icon">🔍</div>
                    <h3>Transparency Always</h3>
                    <p>Every claim is reviewed and verified before going live. Donors always know exactly where their money is going and can track real progress in real time.</p>
                </div>
                <div class="mission-card">
                    <div class="mission-icon">📣</div>
                    <h3>Student Voice</h3>
                    <p>Students aren't just recipients — they're storytellers. They post updates, share their journey, and build real connections with the people supporting them.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── How It Works ──────────────────────────────────── -->
    <section id="how-it-works" class="how-section">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label">How It Works</span>
                <h2>Simple, <span>Transparent</span>, Impactful</h2>
            </div>
            <div class="how-grid">
                <div class="how-card">
                    <div class="how-number">1</div>
                    <div class="how-icon">📝</div>
                    <h3>Submit a Claim</h3>
                    <p>Students create an account, build their profile, and submit a tuition claim with proof of their balance.</p>
                </div>
                <div class="how-card">
                    <div class="how-number">2</div>
                    <div class="how-icon">✅</div>
                    <h3>Admin Review</h3>
                    <p>Our team reviews every claim for authenticity before it goes live on the platform.</p>
                </div>
                <div class="how-card">
                    <div class="how-number">3</div>
                    <div class="how-icon">💳</div>
                    <h3>Donors Give</h3>
                    <p>Donors browse approved claims, read student stories, and contribute any amount toward the balance.</p>
                </div>
                <div class="how-card">
                    <div class="how-number">4</div>
                    <div class="how-icon">🌱</div>
                    <h3>Students Thrive</h3>
                    <p>Students post updates, track funding progress, and stay connected with the people who believed in them.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Why ───────────────────────────────────────────── -->
    <section class="donor-section">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label light">Why The Yard Fund</span>
                <h2>Built Different, <span>On Purpose</span></h2>
            </div>
            <div class="donor-grid">
                <div class="donor-card">
                    <div class="donor-icon-wrap">❤️</div>
                    <h3>100% Direct</h3>
                    <p>Every dollar donated goes directly toward a verified student's outstanding tuition balance.</p>
                </div>
                <div class="donor-card">
                    <div class="donor-icon-wrap">🔒</div>
                    <h3>Verified Claims</h3>
                    <p>Claims are manually reviewed before going public, so donors can give with full confidence.</p>
                </div>
                <div class="donor-card">
                    <div class="donor-icon-wrap">📈</div>
                    <h3>Real-Time Progress</h3>
                    <p>Watch the progress bar move. Read student updates. Know your impact the moment it happens.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Contact ────────────────────────────────────────── -->
    <section id="contact" class="contact-section">
        <div class="section-container">
            <div class="contact-layout">
                <div class="contact-text">
                    <span class="section-label">Contact Us</span>
                    <h2>Get in <span>Touch</span></h2>
                    <p>Have questions about The Yard Fund? Want to partner with us or make a bulk donation? We'd love to hear from you.</p>
                    <div class="contact-details">
                        <div class="contact-item">
                            <span class="contact-icon">📧</span>
                            <div>
                                <strong>Email</strong>
                                <p>hello@yardfund.org</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="contact-icon">📍</span>
                            <div>
                                <strong>Location</strong>
                                <p>Virginia State University<br>Petersburg, Virginia</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="contact-icon">📱</span>
                            <div>
                                <strong>Social</strong>
                                <p>@TheYardFund</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="contact-form-wrap">
                    <div class="page-card">
                        <h3 style="margin-top:0;font-family:'Fredoka',Arial,sans-serif;color:#16233f;">Send Us a Message</h3>
                        <form id="contactForm" onsubmit="handleContact(event)">
                            <label for="c_name">Your Name</label>
                            <input type="text" id="c_name" required placeholder="Full name">
                            <label for="c_email">Email Address</label>
                            <input type="email" id="c_email" required placeholder="you@email.com">
                            <label for="c_subject">Subject</label>
                            <input type="text" id="c_subject" required placeholder="What's this about?">
                            <label for="c_message">Message</label>
                            <textarea id="c_message" rows="4" required placeholder="Tell us more..."></textarea>
                            <button type="submit" class="full-width">Send Message</button>
                            <div id="contactSuccess" class="message" style="display:none;margin-top:12px;">
                                ✅ Message sent! We'll get back to you soon.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($topDonors)): ?>
    <section class="donor-wall-section">
        <div class="section-container">
            <div class="section-header">
                <span class="section-label light">Community</span>
                <h2>Our <span>Top Donors</span></h2>
                <p>These generous members of the VSU community have given the most to support students in need.</p>
            </div>
            <div class="donor-wall-grid">
                <?php foreach ($topDonors as $i => $d): ?>
                    <div class="donor-wall-card">
                        <div class="donor-wall-rank"><?php echo $i + 1; ?></div>
                        <div class="donor-wall-avatar">
                            <?php if (!empty($d['profile_photo'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($d['profile_photo']); ?>"
                                     alt="<?php echo htmlspecialchars($d['full_name']); ?>">
                            <?php else: ?>
                                <div class="donor-wall-initials">
                                    <?php echo strtoupper(substr($d['full_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="donor-wall-info">
                            <h4><?php echo htmlspecialchars($d['full_name']); ?></h4>
                            <p>$<?php echo number_format($d['total_given'], 2); ?> donated</p>
                            <small><?php echo $d['students_supported']; ?> student<?php echo $d['students_supported'] != 1 ? 's' : ''; ?> supported</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="text-align:center;margin-top:28px;color:rgba(255,255,255,.7);font-size:.9rem;">
                Only non-anonymous donations are shown.
                <?php if (!$logged_in): ?>
                    <a href="register.php" style="color:#f98a1e;font-weight:700;">Join them →</a>
                <?php endif; ?>
            </p>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$logged_in): ?>
    <section class="cta-section">
        <div class="section-container">
            <div class="cta-card">
                <h2>Ready to make a difference?</h2>
                <p>Join the VSU community on The Yard Fund — it takes less than a minute to get started.</p>
                <div class="hero-buttons" style="justify-content:center;margin-top:28px;">
                    <a class="hero-btn hero-btn-primary" href="register.php">Create an Account</a>
                    <a class="hero-btn hero-btn-secondary" href="donor_browse_claims.php">Browse Claims First</a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="uploads/yard-fund-logo.png" alt="The Yard Fund" style="height:36px;display:inline-block;vertical-align:middle;">
            <span style="font-family:'Fredoka',Arial,sans-serif;font-size:1.2rem;font-weight:700;color:#fff;margin-left:10px;vertical-align:middle;">The Yard Fund</span>
        </div>
        <div class="footer-links">
            <a href="#about">About</a>
            <a href="#mission">Mission</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#contact">Contact</a>
            <a href="donor_browse_claims.php">Browse Claims</a>
            <?php if (!$logged_in): ?>
                <a href="login.php">Log In</a>
                <a href="register.php">Sign Up</a>
            <?php endif; ?>
        </div>
        <p class="footer-copy">© <?php echo date("Y"); ?> The Yard Fund · Built by VSU students, for VSU students.</p>
    </div>
</footer>

<script>
// Dropdown
const trigger = document.querySelector('.nav-dropdown-trigger');
const menu    = document.querySelector('.nav-dropdown-menu');
if (trigger && menu) {
    trigger.addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('open'); trigger.classList.toggle('active'); });
    document.addEventListener('click', () => { menu.classList.remove('open'); trigger.classList.remove('active'); });
}

// Hamburger
const hamburger  = document.getElementById('navToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => { mobileMenu.classList.toggle('open'); hamburger.classList.toggle('open'); });
    mobileMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { mobileMenu.classList.remove('open'); hamburger.classList.remove('open'); }));
}

// Contact form
function handleContact(e) {
    e.preventDefault();
    document.getElementById('contactSuccess').style.display = 'block';
    e.target.querySelectorAll('input, textarea, button').forEach(el => el.disabled = true);
}
</script>
</body>
</html>
