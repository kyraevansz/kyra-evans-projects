<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_type = $_SESSION['user_type'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

$_pendingCount = 0;
if ($user_type === 'admin') {
    $_pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM claims_pending")->fetchColumn();
}
?>
<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="brand">
            <img src="uploads/yard-fund-logo.png" alt="The Yard Fund" class="brand-logo">
            <span class="brand-text">The Yard Fund</span>
        </a>

        <nav class="main-nav">
            <?php if ($user_type === 'student'): ?>
                <a href="donor_browse_claims.php">Browse Claims</a>
                <a href="index.php#how-it-works">How It Works</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-trigger">
                        <?php echo htmlspecialchars($full_name); ?>
                        <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-menu">
                        <a href="student_dashboard.php">📊 My Dashboard</a>
                        <a href="student_submit_claim.php">📝 My Claim</a>
                        <a href="account_settings.php">⚙️ Account Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-logout">🚪 Log Out</a>
                    </div>
                </div>

            <?php elseif ($user_type === 'donor'): ?>
                <a href="donor_browse_claims.php">Browse Claims</a>
                <a href="index.php#how-it-works">How It Works</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-trigger">
                        <?php echo htmlspecialchars($full_name); ?>
                        <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-menu">
                        <a href="donor_dashboard.php">📊 My Dashboard</a>
                        <a href="donor_browse_claims.php">🔍 Browse Claims</a>
                        <a href="account_settings.php">⚙️ Account Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-logout">🚪 Log Out</a>
                    </div>
                </div>

            <?php elseif ($user_type === 'admin'): ?>
                <a href="admin_pending_claims.php">
                    Pending Claims
                    <?php if ($_pendingCount > 0): ?>
                        <span class="badge-count" style="background:#f98a1e;color:#fff;"><?php echo $_pendingCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_users.php">Users</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-trigger">
                        <?php echo htmlspecialchars($full_name); ?>
                        <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-menu">
                        <a href="admin_dashboard.php">📊 Admin Dashboard</a>
                        <a href="admin_pending_claims.php">📋 Pending Claims</a>
                        <a href="admin_claim_history.php">📁 Claim History</a>
                        <a href="admin_users.php">👥 Manage Users</a>
                        <a href="account_settings.php">⚙️ Account Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-logout">🚪 Log Out</a>
                    </div>
                </div>

            <?php else: ?>
                <a href="index.php#about">About Us</a>
                <a href="index.php#how-it-works">How It Works</a>
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
        <?php if ($user_type === 'student'): ?>
            <a href="student_dashboard.php">📊 My Dashboard</a>
            <a href="student_submit_claim.php">📝 My Claim</a>
            <a href="donor_browse_claims.php">Browse Claims</a>
            <a href="account_settings.php">⚙️ Settings</a>
            <a href="logout.php">🚪 Log Out</a>
        <?php elseif ($user_type === 'donor'): ?>
            <a href="donor_dashboard.php">📊 My Dashboard</a>
            <a href="donor_browse_claims.php">🔍 Browse Claims</a>
            <a href="account_settings.php">⚙️ Settings</a>
            <a href="logout.php">🚪 Log Out</a>
        <?php elseif ($user_type === 'admin'): ?>
            <a href="admin_dashboard.php">📊 Admin Dashboard</a>
            <a href="admin_pending_claims.php">📋 Pending Claims <?php if ($_pendingCount > 0): ?>(<?php echo $_pendingCount; ?>)<?php endif; ?></a>
            <a href="admin_claim_history.php">📁 Claim History</a>
            <a href="admin_users.php">👥 Manage Users</a>
            <a href="account_settings.php">⚙️ Settings</a>
            <a href="logout.php">🚪 Log Out</a>
        <?php else: ?>
            <a href="index.php#about">About Us</a>
            <a href="index.php#how-it-works">How It Works</a>
            <a href="donor_browse_claims.php">Browse Claims</a>
            <a href="login.php">Log In</a>
            <a href="register.php">Sign Up</a>
        <?php endif; ?>
    </div>
</header>

<script>
(function() {
    const trigger = document.querySelector('.nav-dropdown-trigger');
    const menu    = document.querySelector('.nav-dropdown-menu');
    if (trigger && menu) {
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('open');
            trigger.classList.toggle('active');
        });
        document.addEventListener('click', function() {
            menu.classList.remove('open');
            trigger.classList.remove('active');
        });
    }
    const hamburger  = document.getElementById('navToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function() {
            mobileMenu.classList.toggle('open');
            hamburger.classList.toggle('open');
        });
        mobileMenu.querySelectorAll('a').forEach(function(a) {
            a.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                hamburger.classList.remove('open');
            });
        });
    }
})();
</script>
