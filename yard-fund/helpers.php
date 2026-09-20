<?php

/**
 * Render the <head> block for inner pages.
 * Usage: <?php page_head('Dashboard'); ?>
 */
function page_head(string $title): void {
    $full_title = htmlspecialchars($title) . ' - ' . APP_NAME;
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$full_title}</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    HTML;
}

/**
 * Build the "Major · Junior · GPA 3.50" meta string for a user row.
 * Returns a safe, already-htmlspecialchars'd string or a fallback.
 */
function student_meta(array $user, string $fallback = 'Student'): string {
    $parts = array_filter([
        $user['major']          ?? '',
        $user['classification'] ?? '',
        !empty($user['gpa']) ? 'GPA ' . number_format((float)$user['gpa'], 2) : '',
    ]);
    return $parts ? htmlspecialchars(implode(' · ', $parts)) : $fallback;
}

/**
 * Render a progress bar + summary line.
 * $raised and $goal are floats.
 */
function render_progress(float $raised, float $goal): void {
    $progress  = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
    $remaining = max(0, $goal - $raised);
    echo <<<HTML
    <div class="student-progress-row">
        <span>Goal: \${$goal_fmt}</span>
        <span>{$progress}%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:{$progress}%;"></div></div>
    <p class="progress-text">
        \${$raised_fmt} raised &nbsp;·&nbsp; \${$remaining_fmt} remaining
    </p>
    HTML;
    // Note: heredoc doesn't interpolate function calls, so use echo below
}

// Cleaner version using printf-style approach
function render_progress_bar(float $raised, float $goal): void {
    $progress  = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
    $remaining = max(0, $goal - $raised);
    ?>
    <div class="student-progress-row">
        <span>Goal: $<?php echo number_format($goal, 2); ?></span>
        <span><?php echo $progress; ?>%</span>
    </div>
    <div class="progress-bar">
        <div class="progress-fill" style="width:<?php echo $progress; ?>%;"></div>
    </div>
    <p class="progress-text">
        $<?php echo number_format($raised, 2); ?> raised &nbsp;·&nbsp;
        $<?php echo number_format($remaining, 2); ?> remaining
    </p>
    <?php
}

/**
 * Send a "fully funded" email to a student.
 */
function send_funded_email(string $email, string $name): void {
    $body = "
        <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Amazing news — your tuition claim on " . APP_NAME . " has been <strong>fully funded!</strong> 🎉</p>
        <p>Your community showed up for you. Log in to see your final progress and post an update for your donors.</p>
    ";
    sendEmailTemplate(
        $email, $name,
        'Your claim is fully funded!', 'Fully Funded! 🎉',
        $body, 'View My Dashboard', APP_URL . '/student_dashboard.php'
    );
}

/**
 * Simple login rate limiter using PHP sessions.
 * Returns true if the user is currently locked out.
 */
function is_rate_limited(): bool {
    $max_attempts = 5;
    $lockout_secs = 15 * 60; // 15 minutes

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = 0;
    }

    if (time() < $_SESSION['login_locked_until']) {
        return true;
    }

    return false;
}

function record_failed_login(): void {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = time() + (15 * 60);
    }
}

function reset_login_attempts(): void {
    $_SESSION['login_attempts']    = 0;
    $_SESSION['login_locked_until'] = 0;
}

function lockout_seconds_remaining(): int {
    return max(0, (int)($_SESSION['login_locked_until'] ?? 0) - time());
}

/**
 * Calculate student profile completeness as a percentage.
 * Returns ['pct' => int, 'missing' => string[]]
 */
function profile_completeness(array $user): array {
    $fields = [
        'profile_photo'  => 'Profile photo',
        'major'          => 'Major',
        'classification' => 'Classification',
        'gpa'            => 'GPA',
        'bio'            => 'Bio',
    ];
    $missing = [];
    foreach ($fields as $key => $label) {
        if (empty($user[$key])) $missing[] = $label;
    }
    $pct = (int) round(((count($fields) - count($missing)) / count($fields)) * 100);
    return ['pct' => $pct, 'missing' => $missing];
}

/**
 * Render pagination links.
 * $base = URL without page param, e.g. "admin_users.php?filter=all"
 */
function render_pagination(int $total, int $per_page, int $current_page, string $base): void {
    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages <= 1) return;
    echo '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $sep = str_contains($base, '?') ? '&' : '?';
        $url = $base . $sep . 'page=' . $i;
        $active = $i === $current_page ? ' active' : '';
        echo "<a href=\"{$url}\" class=\"page-btn{$active}\">{$i}</a>";
    }
    echo '</div>';
}
