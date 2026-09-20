<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Require login ────────────────────────────────────── */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/* ── Require a specific role ──────────────────────────── */
function require_role(string $role) {
    require_login();
    if ($_SESSION['user_type'] !== $role) {
        header("Location: index.php");
        exit;
    }
}

/* ── Redirect if already logged in ───────────────────── */
function redirect_if_logged_in() {
    if (isset($_SESSION['user_id'])) {
        header("Location: " . dashboard_url());
        exit;
    }
}

/* ── Get dashboard URL for current user ──────────────── */
function dashboard_url(): string {
    $type = $_SESSION['user_type'] ?? '';
    if ($type === 'student') return 'student_dashboard.php';
    if ($type === 'donor')   return 'donor_dashboard.php';
    if ($type === 'admin')   return 'admin_dashboard.php';
    return 'index.php';
}

/* ── Check if user is logged in ──────────────────────── */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/* ── Get current user type ───────────────────────────── */
function current_user_type(): string {
    return $_SESSION['user_type'] ?? '';
}

/* ── Login a user (call after password_verify) ───────── */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
}
