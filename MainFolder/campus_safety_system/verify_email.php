<?php
require_once 'config.php';

// Already logged in — just go to dashboard
if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$status   = 'invalid';  // invalid | expired | already_verified | success
$userName = '';

$token = trim($_GET['token'] ?? '');

if (!empty($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {

    // ── Search university_users ──────────────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT user_id, first_name, email_verified, verification_token_expires_at
         FROM university_users
         WHERE verification_token = ?
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        if ((int)$row['email_verified'] === 1) {
            $status = 'already_verified';
        } elseif (strtotime($row['verification_token_expires_at']) < time()) {
            $status = 'expired';
        } else {
            $upd = $conn->prepare(
                "UPDATE university_users
                 SET email_verified = 1,
                     verification_token = NULL,
                     verification_token_expires_at = NULL
                 WHERE user_id = ?"
            );
            $upd->bind_param("s", $row['user_id']);
            $upd->execute();
            $upd->close();
            $userName = $row['first_name'];
            $status   = 'success';
        }
    }

    // ── If not found, try security_personnel ─────────────────────────────────
    if ($status === 'invalid') {
        $stmt = $conn->prepare(
            "SELECT security_id, first_name, email_verified, verification_token_expires_at
             FROM security_personnel
             WHERE verification_token = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ((int)$row['email_verified'] === 1) {
                $status = 'already_verified';
            } elseif (strtotime($row['verification_token_expires_at']) < time()) {
                $status = 'expired';
            } else {
                $upd = $conn->prepare(
                    "UPDATE security_personnel
                     SET email_verified = 1,
                         verification_token = NULL,
                         verification_token_expires_at = NULL
                     WHERE security_id = ?"
                );
                $upd->bind_param("s", $row['security_id']);
                $upd->execute();
                $upd->close();
                $userName = $row['first_name'];
                $status   = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width:460px;width:100%;border-radius:12px;overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#C0392B,#96281B);padding:28px;text-align:center;">
            <i class="fa-solid fa-shield-halved fa-3x text-white mb-2"></i>
            <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
            <p class="text-white-50 small mb-0">Campus Emergency Response System</p>
        </div>

        <div class="card-body p-4 text-center">

            <?php if ($status === 'success'): ?>
                <div class="mb-3">
                    <span style="font-size:56px;">✅</span>
                </div>
                <h5 class="fw-bold text-success mb-2">Email Verified!</h5>
                <p class="text-muted mb-4">
                    Welcome, <strong><?= htmlspecialchars($userName) ?></strong>!
                    Your account is now active. You can sign in below.
                </p>
                <a href="login.php" class="btn btn-danger w-100 fw-bold">
                    <i class="fa fa-right-to-bracket me-2"></i>Sign In Now
                </a>

            <?php elseif ($status === 'already_verified'): ?>
                <div class="mb-3">
                    <span style="font-size:56px;">ℹ️</span>
                </div>
                <h5 class="fw-bold mb-2">Already Verified</h5>
                <p class="text-muted mb-4">
                    This email address has already been verified.
                    You can go ahead and sign in.
                </p>
                <a href="login.php" class="btn btn-danger w-100 fw-bold">
                    <i class="fa fa-right-to-bracket me-2"></i>Sign In
                </a>

            <?php elseif ($status === 'expired'): ?>
                <div class="mb-3">
                    <span style="font-size:56px;">⏰</span>
                </div>
                <h5 class="fw-bold text-warning mb-2">Link Expired</h5>
                <p class="text-muted mb-4">
                    This verification link has expired (links are valid for 24 hours).
                    Request a fresh link below.
                </p>
                <a href="resend_verification.php" class="btn btn-danger w-100 fw-bold mb-2">
                    <i class="fa fa-envelope me-2"></i>Resend Verification Email
                </a>
                <a href="login.php" class="btn btn-outline-secondary w-100">Back to Login</a>

            <?php else: ?>
                <div class="mb-3">
                    <span style="font-size:56px;">❌</span>
                </div>
                <h5 class="fw-bold text-danger mb-2">Invalid Link</h5>
                <p class="text-muted mb-4">
                    This verification link is invalid or has already been used.
                    If you need a new one, click below.
                </p>
                <a href="resend_verification.php" class="btn btn-danger w-100 fw-bold mb-2">
                    <i class="fa fa-envelope me-2"></i>Resend Verification Email
                </a>
                <a href="login.php" class="btn btn-outline-secondary w-100">Back to Login</a>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
