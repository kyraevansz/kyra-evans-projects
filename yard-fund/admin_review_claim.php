<?php
require 'db.php';
require 'auth.php';
require 'mailer.php';
require 'helpers.php';
require_role('admin');

// Support both GET (approve) and POST (deny with reason)
$claim_id = isset($_GET['id'])  ? (int) $_GET['id']
          : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$action   = $_GET['action'] ?? $_POST['action'] ?? '';

if (!in_array($action, ['approve', 'deny'], true) || $claim_id <= 0) {
    header("Location: admin_pending_claims.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.claim_id, p.student_id, p.tuition_amount, p.description, p.proof_file, p.submitted_at,
           u.full_name, u.email
    FROM claims_pending p
    JOIN users u ON p.student_id = u.user_id
    WHERE p.claim_id = ?
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    header("Location: admin_pending_claims.php?msg=" . urlencode("Claim not found — it may have already been reviewed."));
    exit;
}

if ($action === 'approve') {
    $existing = $pdo->prepare("SELECT claim_id FROM claims_approved WHERE student_id = ?");
    $existing->execute([$claim['student_id']]);
    $approvedRow = $existing->fetch();

    $pdo->beginTransaction();
    try {
        if ($approvedRow) {
            $pdo->prepare("
                UPDATE claims_approved
                SET tuition_amount=?, description=?, proof_file=?, funding_status='open', approved_at=NOW()
                WHERE student_id=?
            ")->execute([$claim['tuition_amount'], $claim['description'], $claim['proof_file'], $claim['student_id']]);
            $note = "Your existing approved claim has been updated with the new amount.";
        } else {
            $pdo->prepare("
                INSERT INTO claims_approved (student_id, tuition_amount, description, proof_file, funding_status, approved_at)
                VALUES (?,?,?,?,'open',NOW())
            ")->execute([$claim['student_id'], $claim['tuition_amount'], $claim['description'], $claim['proof_file']]);
            $note = "Your claim is now visible to donors on " . APP_NAME . ".";
        }
        $pdo->prepare("DELETE FROM claims_pending WHERE claim_id=?")->execute([$claim_id]);
        $pdo->commit();

        $result = sendEmailTemplate(
            $claim['email'], $claim['full_name'],
            'Your ' . APP_NAME . ' claim was approved', 'Claim Approved',
            "<p>Hello <strong>" . htmlspecialchars($claim['full_name']) . "</strong>,</p>
             <p>Your tuition claim has been <strong>approved</strong>.</p>
             <p><strong>Amount:</strong> $" . number_format($claim['tuition_amount'], 2) . "<br>
             <strong>Description:</strong> " . htmlspecialchars($claim['description']) . "</p>
             <p>" . $note . "</p>",
            'View My Dashboard', APP_URL . '/student_dashboard.php'
        );

        $msg = "Claim approved for " . $claim['full_name'] . "." . ($result === true ? " Email sent." : " (Email failed: $result)");

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error: " . $e->getMessage();
    }

} else {
    // Deny — capture reason from POST
    $denial_reason = trim($_POST['denial_reason'] ?? '');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO claims_denied (student_id, tuition_amount, description, proof_file, denial_reason, submitted_at, denied_at)
            VALUES (?,?,?,?,?,?,NOW())
        ")->execute([
            $claim['student_id'], $claim['tuition_amount'],
            $claim['description'], $claim['proof_file'],
            $denial_reason ?: null, $claim['submitted_at']
        ]);
        $pdo->prepare("DELETE FROM claims_pending WHERE claim_id=?")->execute([$claim_id]);
        $pdo->commit();

        $reasonHtml = $denial_reason
            ? "<p><strong>Reason:</strong> " . htmlspecialchars($denial_reason) . "</p>
               <p>Please address this and resubmit your claim from your dashboard.</p>"
            : "<p>Please contact the administrator if you believe this was an error.</p>";

        $result = sendEmailTemplate(
            $claim['email'], $claim['full_name'],
            'Your ' . APP_NAME . ' claim was denied', 'Claim Denied',
            "<p>Hello <strong>" . htmlspecialchars($claim['full_name']) . "</strong>,</p>
             <p>Your tuition claim has been <strong>denied</strong>.</p>
             <p><strong>Amount:</strong> $" . number_format($claim['tuition_amount'], 2) . "<br>
             <strong>Description:</strong> " . htmlspecialchars($claim['description']) . "</p>
             {$reasonHtml}",
            'View My Dashboard', APP_URL . '/student_dashboard.php'
        );

        $msg = "Claim denied for " . $claim['full_name'] . "." . ($result === true ? " Email sent." : " (Email failed: $result)");

    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error: " . $e->getMessage();
    }
}

header("Location: admin_pending_claims.php?msg=" . urlencode($msg));
exit;
