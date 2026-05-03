<?php
require_once 'config.php';
require_once 'mailer.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$sent   = false;
$errors = [];

// Pre-fill from GET params (linked from login page unverified-email warning)
$prefillEmail = htmlspecialchars(trim($_GET['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$prefillType  = in_array($_GET['type'] ?? '', ['university', 'security']) ? $_GET['type'] : 'university';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("resend_verify_{$ip}", 3, 900)) { // 3 per 15 min
        $errors[] = "Too many requests. Please wait 15 minutes before trying again.";
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $userType = in_array($_POST['user_type'] ?? '', ['university', 'security'])
                    ? $_POST['user_type'] : 'university';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($errors)) {
            $token   = generateSecureToken();
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $found   = false;

            if ($userType === 'university') {
                $stmt = $conn->prepare(
                    "SELECT user_id, first_name, email_verified
                     FROM university_users WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row && !(int)$row['email_verified']) {
                    $upd = $conn->prepare(
                        "UPDATE university_users
                         SET verification_token = ?,
                             verification_token_expires_at = ?
                         WHERE user_id = ?"
                    );
                    $upd->bind_param("sss", $token, $expires, $row['user_id']);
                    $upd->execute();
                    $upd->close();
                    sendVerificationEmail($email, $row['first_name'], $token);
                    $found = true;
                }
            } else {
                $stmt = $conn->prepare(
                    "SELECT security_id, first_name, email_verified
                     FROM security_personnel WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row && !(int)$row['email_verified']) {
                    $upd = $conn->prepare(
                        "UPDATE security_personnel
                         SET verification_token = ?,
                             verification_token_expires_at = ?
                         WHERE security_id = ?"
                    );
                    $upd->bind_param("sss", $token, $expires, $row['security_id']);
                    $upd->execute();
                    $upd->close();
                    sendVerificationEmail($email, $row['first_name'], $token);
                    $found = true;
                }
            }

            // Always show success — prevents email enumeration
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width:460px;width:100%;border-radius:12px;overflow:hidden;">

        <div style="background:linear-gradient(135deg,#C0392B,#96281B);padding:28px;text-align:center;">
            <i class="fa-solid fa-shield-halved fa-3x text-white mb-2"></i>
            <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
            <p class="text-white-50 small mb-0">Campus Emergency Response System</p>
        </div>

        <div class="card-body p-4">

            <?php if ($sent): ?>
                <div class="text-center py-2">
                    <span style="font-size:48px;">📧</span>
                    <h5 class="fw-bold mt-3 mb-2">Check Your Inbox</h5>
                    <p class="text-muted mb-4">
                        If that email address is registered and unverified, we have sent a fresh
                        verification link. It will expire in <strong>24 hours</strong>.
                    </p>
                    <a href="login.php" class="btn btn-danger w-100 fw-bold">
                        <i class="fa fa-right-to-bracket me-2"></i>Back to Login
                    </a>
                </div>

            <?php else: ?>
                <div class="mb-1">
                    <a href="login.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fa fa-arrow-left"></i>
                    </a>
                    <span class="fw-bold fs-5">Resend Verification Email</span>
                </div>
                <p class="text-muted small mb-4 mt-1">
                    Enter your registered email address and we will send you a new verification link.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $e): ?>
                            <div><i class="fa fa-circle-xmark me-1"></i><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="resend_verification.php">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" required
                                   maxlength="150" placeholder="you@university.edu.gh"
                                   value="<?= $prefillEmail ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Account Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="user_type"
                                       id="typeUniversity" value="university"
                                       <?= $prefillType === 'university' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="typeUniversity">
                                    <i class="fa fa-graduation-cap me-1"></i>University User
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="user_type"
                                       id="typeSecurity" value="security"
                                       <?= $prefillType === 'security' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="typeSecurity">
                                    <i class="fa fa-shield me-1"></i>Security Personnel
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold">
                        <i class="fa fa-paper-plane me-2"></i>Send Verification Email
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
