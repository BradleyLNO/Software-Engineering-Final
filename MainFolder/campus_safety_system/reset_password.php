<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$token       = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenValid  = false;
$resetDone   = false;
$errors      = [];

// Identify which table and row owns this token
$ownerTable  = null;  // 'university' | 'security'
$ownerId     = null;
$ownerName   = '';

if (!empty($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {

    // Search university_users
    $stmt = $conn->prepare(
        "SELECT user_id, first_name, reset_token_expires_at
         FROM university_users
         WHERE reset_token = ?
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && strtotime($row['reset_token_expires_at']) >= time()) {
        $tokenValid  = true;
        $ownerTable  = 'university';
        $ownerId     = $row['user_id'];
        $ownerName   = $row['first_name'];
    }

    // If not found, try security_personnel
    if (!$tokenValid) {
        $stmt = $conn->prepare(
            "SELECT security_id, first_name, reset_token_expires_at
             FROM security_personnel
             WHERE reset_token = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && strtotime($row['reset_token_expires_at']) >= time()) {
            $tokenValid  = true;
            $ownerTable  = 'security';
            $ownerId     = $row['security_id'];
            $ownerName   = $row['first_name'];
        }
    }
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("reset_pw_{$ip}", 5, 300)) {
        $errors[] = "Too many attempts. Please wait a few minutes.";
    } else {
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $pwErrors = validatePassword($password);
        $errors   = array_merge($errors, $pwErrors);

        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            if ($ownerTable === 'university') {
                $upd = $conn->prepare(
                    "UPDATE university_users
                     SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL
                     WHERE user_id = ?"
                );
                $upd->bind_param("ss", $newHash, $ownerId);
            } else {
                $upd = $conn->prepare(
                    "UPDATE security_personnel
                     SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL
                     WHERE security_id = ?"
                );
                $upd->bind_param("ss", $newHash, $ownerId);
            }

            if ($upd->execute()) {
                $upd->close();
                setFlash('success', 'Your password has been reset successfully. Please sign in with your new password.');
                header('Location: login.php');
                exit();
            } else {
                $upd->close();
                $errors[] = "Password update failed. Please try again.";
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
    <title>Reset Password — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width:480px;width:100%;border-radius:12px;overflow:hidden;">

        <div style="background:linear-gradient(135deg,#C0392B,#96281B);padding:28px;text-align:center;">
            <i class="fa-solid fa-shield-halved fa-3x text-white mb-2"></i>
            <h4 class="text-white fw-bold mb-0"><?= APP_NAME ?></h4>
            <p class="text-white-50 small mb-0">Campus Emergency Response System</p>
        </div>

        <div class="card-body p-4">

            <?php if (!$tokenValid): ?>
                <!-- Invalid / expired token -->
                <div class="text-center py-2">
                    <span style="font-size:48px;">⏰</span>
                    <h5 class="fw-bold mt-3 mb-2 text-danger">Link Invalid or Expired</h5>
                    <p class="text-muted mb-4">
                        This password reset link is invalid or has expired (links are valid for 1 hour).
                        Request a new one below.
                    </p>
                    <a href="forgot_password.php" class="btn btn-danger w-100 fw-bold mb-2">
                        <i class="fa fa-key me-2"></i>Request New Reset Link
                    </a>
                    <a href="login.php" class="btn btn-outline-secondary w-100">Back to Login</a>
                </div>

            <?php else: ?>
                <!-- Valid token — show form -->
                <div class="mb-1">
                    <span class="fw-bold fs-5">
                        <i class="fa fa-lock me-2 text-danger"></i>Set a New Password
                    </span>
                </div>
                <p class="text-muted small mb-4 mt-1">
                    Hi <strong><?= htmlspecialchars($ownerName) ?></strong>, choose a strong new password below.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Please fix the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reset_password.php" id="resetForm" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password"
                                   required minlength="8" maxlength="255" placeholder="Min 8 chars"
                                   autocomplete="new-password">
                            <button type="button" class="input-group-text btn-toggle-pw"
                                    onclick="togglePw('password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="strengthBar"></div>
                        <div class="form-text mt-1" id="pwHints">
                            <span id="hint-len"   class="pw-hint">✗ 8+ characters</span>
                            <span id="hint-upper" class="pw-hint">✗ Uppercase</span>
                            <span id="hint-lower" class="pw-hint">✗ Lowercase</span>
                            <span id="hint-num"   class="pw-hint">✗ Number</span>
                            <span id="hint-sym"   class="pw-hint">✗ Symbol</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Confirm New Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock-open"></i></span>
                            <input type="password" class="form-control" name="confirm_password"
                                   id="confirm_password" required maxlength="255"
                                   placeholder="Re-enter password" autocomplete="new-password">
                            <button type="button" class="input-group-text btn-toggle-pw"
                                    onclick="togglePw('confirm_password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text mt-1" id="matchMsg"></div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold">
                        <i class="fa fa-floppy-disk me-2"></i>Save New Password
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(id, btn) {
    const f = document.getElementById(id);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

const pwField = document.getElementById('password');
if (pwField) {
    pwField.addEventListener('input', function () {
        const val   = this.value;
        const tests = {
            len:   val.length >= 8,
            upper: /[A-Z]/.test(val),
            lower: /[a-z]/.test(val),
            num:   /[0-9]/.test(val),
            sym:   /[\W_]/.test(val),
        };
        const passed = Object.values(tests).filter(Boolean).length;
        const bar    = document.getElementById('strengthBar');

        Object.entries(tests).forEach(([key, ok]) => {
            const el = document.getElementById('hint-' + key);
            el.textContent = (ok ? '✓ ' : '✗ ') + el.textContent.slice(2);
            el.classList.toggle('hint-ok', ok);
            el.classList.toggle('hint-fail', !ok);
        });
        bar.className = 'password-strength mt-2 strength-' + passed;
    });

    document.getElementById('confirm_password').addEventListener('input', function () {
        const msg = document.getElementById('matchMsg');
        if (this.value === pwField.value) {
            msg.innerHTML = '<span class="text-success"><i class="fa fa-check me-1"></i>Passwords match</span>';
        } else {
            msg.innerHTML = '<span class="text-danger"><i class="fa fa-xmark me-1"></i>Passwords do not match</span>';
        }
    });

    document.getElementById('resetForm').addEventListener('submit', function (e) {
        const pw  = pwField.value;
        const cpw = document.getElementById('confirm_password').value;
        const checks = [/[A-Z]/, /[a-z]/, /[0-9]/, /[\W_]/];
        if (pw !== cpw || pw.length < 8 || !checks.every(r => r.test(pw))) {
            e.preventDefault();
            this.classList.add('was-validated');
        }
    });
}
</script>
</body>
</html>
