<?php
require 'db.php';
require 'auth.php';
require 'mailer.php';
require 'helpers.php';
require_role('donor');

$donor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: donor_browse_claims.php");
    exit;
}

$claim_id     = (int)   ($_POST['claim_id']    ?? 0);
$amount_sent  = (float) ($_POST['amount_sent'] ?? 0);
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

if ($claim_id <= 0 || $amount_sent <= 0) {
    header("Location: donor_browse_claims.php");
    exit;
}

// Fetch claim + student
$stmt = $pdo->prepare("
    SELECT a.claim_id, a.tuition_amount, a.funding_status,
           u.full_name AS student_name, u.email AS student_email, u.user_id AS student_id,
           COALESCE(SUM(t.amount_sent), 0) AS total_donated,
           (a.tuition_amount - COALESCE(SUM(t.amount_sent), 0)) AS remaining_balance
    FROM claims_approved a
    JOIN users u ON a.student_id = u.user_id
    LEFT JOIN transactions t ON a.claim_id = t.claim_id
    WHERE a.claim_id = ? AND a.funding_status = 'open'
    GROUP BY a.claim_id, a.tuition_amount, a.funding_status, u.full_name, u.email, u.user_id
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    header("Location: donor_browse_claims.php");
    exit;
}

$remaining = (float) $claim['remaining_balance'];
if ($amount_sent > $remaining) {
    header("Location: student_profile.php?id=" . $claim['student_id'] . "&error=exceeds");
    exit;
}

$donorStmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$donorStmt->execute([$donor_id]);
$donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO transactions (claim_id, donor_id, amount_sent, is_anonymous) VALUES (?, ?, ?, ?)")
        ->execute([$claim_id, $donor_id, $amount_sent, $is_anonymous]);

    $nowFunded = ($remaining - $amount_sent) <= 0;
    if ($nowFunded) {
        $pdo->prepare("UPDATE claims_approved SET funding_status = 'funded' WHERE claim_id = ?")
            ->execute([$claim_id]);
    }

    $pdo->commit();

    // Donor receipt
    sendEmailTemplate(
        $donor['email'], $donor['full_name'],
        'Your donation receipt - ' . APP_NAME, 'Donation Confirmed',
        "<p>Hello <strong>" . htmlspecialchars($donor['full_name']) . "</strong>,</p>
         <p>Thank you for your donation through " . APP_NAME . "!</p>
         <p><strong>Amount:</strong> $" . number_format($amount_sent, 2) . "<br>
         <strong>Student:</strong> " . htmlspecialchars($claim['student_name']) . "<br>
         <strong>Anonymous:</strong> " . ($is_anonymous ? 'Yes' : 'No') . "</p>",
        'View My Dashboard', APP_URL . '/donor_dashboard.php'
    );

    // Student notification
    sendEmailTemplate(
        $claim['student_email'], $claim['student_name'],
        'You received a new donation - ' . APP_NAME, 'New Donation!',
        "<p>Hello <strong>" . htmlspecialchars($claim['student_name']) . "</strong>,</p>
         <p>You received a donation of <strong>$" . number_format($amount_sent, 2) . "</strong>!</p>
         <p><strong>From:</strong> " . ($is_anonymous ? 'Anonymous Donor' : htmlspecialchars($donor['full_name'])) . "</p>",
        'View My Dashboard', APP_URL . '/student_dashboard.php'
    );

    // Fully funded email
    if ($nowFunded) {
        send_funded_email($claim['student_email'], $claim['student_name']);
    }

    header("Location: student_profile.php?id=" . $claim['student_id'] . "&donated=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: student_profile.php?id=" . $claim['student_id'] . "&error=failed");
    exit;
}
