<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

// Read token from session (set during registration)
$verifyToken = $_SESSION['_pv_token'] ?? null;
$sessionEmail = $_SESSION['_pv_email'] ?? null;
unset($_SESSION['_pv_token'], $_SESSION['_pv_email']);

$verifyUrl = $verifyToken
    ? BASE_URL . '/verify_email.php?token=' . rawurlencode($verifyToken)
    : null;

$email = htmlspecialchars(urldecode($_GET['email'] ?? $sessionEmail ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width:500px;width:100%;border-radius:12px;overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#C0392B,#96281B);padding:32px;text-align:center;">
            <i class="fa-solid fa-shield-halved fa-3x text-white mb-2"></i>
            <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
            <p class="text-white-50 small mb-0">Campus Emergency Response System</p>
        </div>

        <div class="card-body p-4">

            <div class="text-center mb-4">
                <div style="font-size:56px;" class="mb-2">📧</div>
                <h4 class="fw-bold mb-1">One Last Step!</h4>
                <p class="text-muted mb-0">Your account is ready. Please verify your email to activate it.</p>
            </div>

            <?php if ($email): ?>
                <div class="alert alert-light border text-center mb-4">
                    <small class="text-muted d-block mb-1">Verification email sent to:</small>
                    <strong><?= $email ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($verifyUrl): ?>
                <!-- STEP 1: Direct verify button (works immediately on this machine) -->
                <div class="card border-success mb-4">
                    <div class="card-body text-center p-4">
                        <div class="mb-2" style="font-size:32px;">✅</div>
                        <h6 class="fw-bold text-success mb-2">Option 1 — Verify Right Now</h6>
                        <p class="text-muted small mb-3">
                            Click the button below on this same computer to instantly verify your account.
                        </p>
                        <a href="<?= htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="btn btn-success btn-lg w-100 fw-bold">
                            <i class="fa fa-circle-check me-2"></i>Verify My Email Now
                        </a>
                    </div>
                </div>

                <!-- STEP 2: Email option -->
                <div class="card border-secondary mb-3" style="opacity:.85;">
                    <div class="card-body text-center p-3">
                        <div class="mb-1" style="font-size:24px;">📬</div>
                        <h6 class="fw-bold mb-1">Option 2 — Use the Email Link</h6>
                        <p class="text-muted small mb-0">
                            Check your inbox for the verification email and click
                            <strong>"Verify My Email"</strong> inside it.
                            Open it on this same computer — the link won't work on a phone.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fa fa-envelope me-2"></i>
                    Check your inbox at <strong><?= $email ?></strong> and click the
                    <strong>"Verify My Email"</strong> button in the email.
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-2">
                <a href="login.php" class="btn btn-outline-secondary flex-fill">
                    <i class="fa fa-right-to-bracket me-1"></i>Go to Login
                </a>
                <?php if ($email): ?>
                    <a href="resend_verification.php?email=<?= rawurlencode(urldecode($_GET['email'] ?? $email)) ?>"
                       class="btn btn-outline-danger flex-fill">
                        <i class="fa fa-paper-plane me-1"></i>Resend Email
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
