<?php
require_once 'config.php';
require_once 'mailer.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("forgot_pw_{$ip}", 3, 900)) { // 3 per 15 min
        $errors[] = "Too many requests. Please wait 15 minutes before trying again.";
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($errors)) {
            $token   = generateSecureToken();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Search university_users
            $stmt = $conn->prepare(
                "SELECT user_id, first_name FROM university_users
                 WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $upd = $conn->prepare(
                    "UPDATE university_users
                     SET reset_token = ?, reset_token_expires_at = ?
                     WHERE user_id = ?"
                );
                $upd->bind_param("sss", $token, $expires, $row['user_id']);
                $upd->execute();
                $upd->close();
                sendPasswordResetEmail($email, $row['first_name'], $token);
            } else {
                // Search security_personnel
                $stmt = $conn->prepare(
                    "SELECT security_id, first_name FROM security_personnel
                     WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $upd = $conn->prepare(
                        "UPDATE security_personnel
                         SET reset_token = ?, reset_token_expires_at = ?
                         WHERE security_id = ?"
                    );
                    $upd->bind_param("sss", $token, $expires, $row['security_id']);
                    $upd->execute();
                    $upd->close();
                    sendPasswordResetEmail($email, $row['first_name'], $token);
                }
            }

            // Always show the same success message — prevents email enumeration
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
    <title>Forgot Password — <?= APP_NAME ?></title>
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
                    <h5 class="fw-bold mt-3 mb-2">Check Your Email</h5>
                    <p class="text-muted mb-4">
                        If that email address is registered, we have sent you a password reset link.
                        The link will expire in <strong>1 hour</strong>.
                    </p>
                    <p class="text-muted small mb-4">
                        Did not receive it? Check your spam folder, or
                        <a href="forgot_password.php" class="text-danger">try again</a>.
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
                    <span class="fw-bold fs-5">Forgot Password</span>
                </div>
                <p class="text-muted small mb-4 mt-1">
                    Enter your registered email address and we will send you a link to reset your password.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $e): ?>
                            <div><i class="fa fa-circle-xmark me-1"></i><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="forgot_password.php">
                    <?= csrfField() ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" required
                                   maxlength="150" placeholder="you@university.edu.gh"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-text">
                            Works for both University Users and Security Personnel accounts.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold">
                        <i class="fa fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Remembered your password?
                    <a href="login.php" class="text-danger fw-semibold">Sign in</a>
                </p>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
