<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require 'mailer.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$message    = $error = "";

if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'submitted' ? "Claim submitted! Pending admin review."
             : "Claim updated and resubmitted for review.";
}

$student = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$student->execute([$student_id]);
$student = $student->fetch(PDO::FETCH_ASSOC);

$pendingStmt = $pdo->prepare("SELECT * FROM claims_pending WHERE student_id=? ORDER BY submitted_at DESC LIMIT 1");
$pendingStmt->execute([$student_id]);
$pendingClaim = $pendingStmt->fetch(PDO::FETCH_ASSOC);

$approvedStmt = $pdo->prepare("SELECT * FROM claims_approved WHERE student_id=?");
$approvedStmt->execute([$student_id]);
$approvedClaim = $approvedStmt->fetch(PDO::FETCH_ASSOC);

// Prefill from a denied claim if ?resubmit=ID is passed
$prefill = ['tuition_amount' => '', 'description' => ''];
$resubmitId = (int) ($_GET['resubmit'] ?? 0);
if ($resubmitId > 0 && !$pendingClaim) {
    $rs = $pdo->prepare("SELECT tuition_amount, description FROM claims_denied WHERE claim_id=? AND student_id=?");
    $rs->execute([$resubmitId, $student_id]);
    $rsRow = $rs->fetch(PDO::FETCH_ASSOC);
    if ($rsRow) $prefill = $rsRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $major          = trim($_POST['major'] ?? '');
    $gpa            = trim($_POST['gpa'] ?? '');
    $classification = trim($_POST['classification'] ?? '');
    $bio            = trim($_POST['bio'] ?? '');
    $tuition_amount = $_POST['tuition_amount'] ?? '';
    $description    = trim($_POST['description'] ?? '');

    if ($gpa !== '' && (!is_numeric($gpa) || $gpa < 0 || $gpa > 4.0)) {
        $error = "GPA must be between 0.00 and 4.00.";
    } else {
        $pdo->prepare("UPDATE users SET major=?, gpa=?, classification=?, bio=? WHERE user_id=?")
            ->execute([$major, ($gpa === '' ? null : $gpa), $classification, $bio, $student_id]);

        if ($_POST['form_action'] === 'claim') {
            if (empty($tuition_amount) || !is_numeric($tuition_amount) || $tuition_amount <= 0) {
                $error = "Please enter a valid tuition amount.";
            } elseif (empty($description)) {
                $error = "Please enter a description.";
            } else {
                $newFileName = $pendingClaim['proof_file'] ?? null;

                if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === 0) {
                    $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
                    $mime    = mime_content_type($_FILES['proof_file']['tmp_name']);
                    if (!in_array($mime, $allowed)) {
                        $error = "Proof must be JPG, PNG, or PDF.";
                    } elseif ($_FILES['proof_file']['size'] > 5 * 1024 * 1024) {
                        $error = "File must be under 5MB.";
                    } else {
                        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                        $ext         = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                        $newFileName = 'proof_' . $student_id . '_' . time() . '.' . $ext;
                        if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], 'uploads/' . $newFileName)) {
                            $error = "File upload failed.";
                        }
                    }
                }

                if (empty($error)) {
                    if ($pendingClaim) {
                        // Save snapshot of current claim to history before overwriting
                        $pdo->prepare("
                            INSERT INTO claims_history (student_id, tuition_amount, description, proof_file, saved_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ")->execute([
                            $student_id,
                            $pendingClaim['tuition_amount'],
                            $pendingClaim['description'],
                            $pendingClaim['proof_file']
                        ]);

                        $pdo->prepare("UPDATE claims_pending SET tuition_amount=?, description=?, proof_file=?, submitted_at=NOW() WHERE claim_id=?")
                            ->execute([$tuition_amount, $description, $newFileName, $pendingClaim['claim_id']]);
                        header("Location: student_submit_claim.php?msg=updated");
                    } elseif (!$newFileName) {
                        $error = "Please upload proof of your tuition balance.";
                    } else {
                        $pdo->prepare("INSERT INTO claims_pending (student_id, tuition_amount, description, proof_file) VALUES (?,?,?,?)")
                            ->execute([$student_id, $tuition_amount, $description, $newFileName]);

                        // Email admin about new submission
                        $admin = $pdo->query("SELECT email, full_name FROM users WHERE user_type='admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                        if ($admin) {
                            sendEmailTemplate(
                                $admin['email'], $admin['full_name'],
                                'New claim submitted - ' . APP_NAME, 'New Claim Submitted',
                                "<p>A new tuition claim has been submitted and is awaiting your review.</p>
                                 <p><strong>Student:</strong> " . htmlspecialchars($student['full_name']) . "<br>
                                 <strong>Amount:</strong> $" . number_format($tuition_amount, 2) . "<br>
                                 <strong>Description:</strong> " . htmlspecialchars($description) . "</p>",
                                'Review Pending Claims', APP_URL . '/admin_pending_claims.php'
                            );
                        }
                        header("Location: student_submit_claim.php?msg=submitted");
                    }
                    if (empty($error)) exit;
                }
            }
        }
    }
}

page_head($pendingClaim ? 'Update Claim' : 'Submit Claim');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">
        <h2><?php echo $pendingClaim ? 'Update Your Claim' : 'Submit a Tuition Claim'; ?></h2>

        <div class="inline-actions" style="margin-bottom:20px;">
            <a class="button secondary" href="student_dashboard.php">← Dashboard</a>
        </div>

        <?php if ($approvedClaim): ?>
            <div class="message">
                You have an approved claim for <strong>$<?php echo number_format($approvedClaim['tuition_amount'], 2); ?></strong>.
                Submitting a new claim requests an amount update — admin must approve it first.
            </div>
        <?php endif; ?>
        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <h3 style="margin-top:0;">Your Profile</h3>
            <p style="color:#64748b;margin-top:-8px;margin-bottom:20px;">Shown on your public profile for donors to see.</p>

            <div class="form-grid">
                <div>
                    <label for="major">Major</label>
                    <input type="text" name="major" id="major"
                        value="<?php echo htmlspecialchars($student['major'] ?? ''); ?>">
                </div>
                <div>
                    <label for="classification">Classification</label>
                    <input type="text" name="classification" id="classification"
                        placeholder="Freshman, Sophomore, Junior, Senior"
                        value="<?php echo htmlspecialchars($student['classification'] ?? ''); ?>">
                </div>
                <div>
                    <label for="gpa">GPA</label>
                    <input type="number" step="0.01" min="0" max="4.00" name="gpa" id="gpa"
                        value="<?php echo $student['gpa'] !== null ? htmlspecialchars($student['gpa']) : ''; ?>">
                </div>
            </div>

            <label for="bio">Short Bio</label>
            <textarea name="bio" id="bio" rows="3"
                placeholder="Tell donors a little about yourself"><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea>

            <h3>Claim Details</h3>

            <label for="tuition_amount">Tuition Balance Owed ($)</label>
            <input type="number" step="0.01" min="0.01" name="tuition_amount" id="tuition_amount" required
                value="<?php echo $pendingClaim ? htmlspecialchars($pendingClaim['tuition_amount']) : htmlspecialchars($prefill['tuition_amount']); ?>">

            <label for="description">Description</label>
            <textarea name="description" id="description" rows="4" required
                placeholder="Explain your situation and why you need support"><?php echo $pendingClaim ? htmlspecialchars($pendingClaim['description']) : htmlspecialchars($prefill['description']); ?></textarea>

            <label for="proof_file"><?php echo $pendingClaim ? 'Upload New Proof (optional)' : 'Upload Proof *'; ?></label>
            <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                <?php echo $pendingClaim ? '' : 'required'; ?>>
            <p class="field-hint">JPG, PNG, or PDF — max 5MB</p>

            <?php if ($pendingClaim && !empty($pendingClaim['proof_file'])): ?>
                <p><strong>Current proof:</strong>
                    <a href="uploads/<?php echo urlencode($pendingClaim['proof_file']); ?>" target="_blank">View file</a>
                </p>
            <?php endif; ?>

            <input type="hidden" name="form_action" value="claim">
            <button type="submit"><?php echo $pendingClaim ? 'Update Claim' : 'Submit Claim'; ?></button>
        </form>
    </div>
</div>
</body></html>
