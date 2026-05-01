<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$email = htmlspecialchars(urldecode($_GET['email'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Your Email — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width:480px;width:100%;border-radius:12px;overflow:hidden;">

        <div style="background:linear-gradient(135deg,#C0392B,#96281B);padding:32px;text-align:center;">
            <i class="fa-solid fa-shield-halved fa-3x text-white mb-2"></i>
            <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
            <p class="text-white-50 small mb-0">Campus Emergency Response System</p>
        </div>

        <div class="card-body p-4 text-center">

            <div class="mb-3" style="font-size:64px;">📧</div>

            <h4 class="fw-bold mb-2">Verify Your Email Address</h4>

            <p class="text-muted mb-2">
                Your account has been created. We sent a verification link to:
            </p>

            <?php if ($email): ?>
                <div class="alert alert-light border fw-semibold mb-3" style="font-size:1.05rem;">
                    <?= $email ?>
                </div>
            <?php endif; ?>

            <p class="text-muted mb-4" style="font-size:0.95rem;">
                Open that email and click the <strong>"Verify My Email"</strong> button
                to activate your account. After verifying, return here to sign in.
            </p>

            <div class="alert alert-warning text-start small mb-4">
                <i class="fa fa-triangle-exclamation me-1"></i>
                <strong>Important:</strong> The link in the email only works on the
                <strong>same computer and browser</strong> where you are accessing this
                system. Do not open the link on a phone or a different computer.
                <br><br>
                <i class="fa fa-folder me-1"></i>
                If you don't see the email, check your <strong>Spam / Junk</strong> folder.
            </div>

            <a href="login.php" class="btn btn-danger w-100 fw-bold mb-3">
                <i class="fa fa-right-to-bracket me-2"></i>Go to Login
            </a>

            <?php if ($email): ?>
                <a href="resend_verification.php?email=<?= rawurlencode(urldecode($_GET['email'] ?? '')) ?>"
                   class="btn btn-outline-secondary w-100">
                    <i class="fa fa-paper-plane me-2"></i>Resend Verification Email
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
