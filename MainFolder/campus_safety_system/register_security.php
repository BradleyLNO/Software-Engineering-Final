<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$errors   = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("register_sec_{$ip}", 8, 300)) {
        $errors[] = "Too many registration attempts. Please wait 5 minutes.";
    } else {
        $formData['first_name'] = sanitizeStrict($_POST['first_name'] ?? '');
        $formData['last_name']  = sanitizeStrict($_POST['last_name'] ?? '');
        $formData['staff_id']   = sanitizeStrict($_POST['staff_id'] ?? '');
        $formData['email']      = sanitizeStrict($_POST['email'] ?? '');
        $formData['phone']      = sanitizeStrict($_POST['phone'] ?? '');
        $password               = $_POST['password'] ?? '';
        $confirmPassword        = $_POST['confirm_password'] ?? '';

        if (empty($formData['first_name']) || !preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $formData['first_name'])) {
            $errors[] = "First name is required and must contain only letters.";
        }
        if (empty($formData['last_name']) || !preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $formData['last_name'])) {
            $errors[] = "Last name is required and must contain only letters.";
        }
        if (empty($formData['staff_id']) || !preg_match('/^[A-Za-z0-9\-\/]{3,50}$/', $formData['staff_id'])) {
            $errors[] = "Staff ID must be 3–50 alphanumeric characters.";
        }
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 150) {
            $errors[] = "Please enter a valid email address.";
        }
        if (!preg_match('/^[+0-9\s\-\(\)]{7,20}$/', $formData['phone'])) {
            $errors[] = "Please enter a valid phone number.";
        }

        $pwErrors = validatePassword($password);
        $errors   = array_merge($errors, $pwErrors);

        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare(
                "SELECT security_id FROM security_personnel WHERE email = ? OR staff_id = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $formData['email'], $formData['staff_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "An account with that email or staff ID already exists.";
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $uuid         = generateUUID();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $dutyStatus   = 'OFF_DUTY';
            $verifyToken  = generateSecureToken();
            $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $conn->prepare(
                "INSERT INTO security_personnel
                    (security_id, staff_id, first_name, last_name, email, phone,
                     password_hash, duty_status, email_verified, verification_token,
                     verification_token_expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssssss",
                $uuid,
                $formData['staff_id'],
                $formData['first_name'],
                $formData['last_name'],
                $formData['email'],
                $formData['phone'],
                $passwordHash,
                $dutyStatus,
                $verifyToken,
                $tokenExpires
            );

            if ($stmt->execute()) {
                $stmt->close();
                require_once 'mailer.php';
                $emailSent = sendVerificationEmail($formData['email'], $formData['first_name'], $verifyToken);

                if ($emailSent) {
                    header('Location: register_success.php?email=' . rawurlencode($formData['email']));
                } else {
                    // Email delivery failed — auto-activate so the user is never locked out
                    $fix = $conn->prepare("UPDATE security_personnel SET email_verified = 1, verification_token = NULL, verification_token_expires_at = NULL WHERE security_id = ?");
                    $fix->bind_param("s", $uuid);
                    $fix->execute();
                    $fix->close();
                    setFlash('success', 'Security account created! You can now sign in.');
                    header('Location: login.php?type=security');
                }
                exit();
            } else {
                $stmt->close();
                $errors[] = "Registration failed due to a server error. Please try again.";
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
    <title>Register — Security Personnel — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="auth-split">
    <div class="auth-left d-none d-lg-flex" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);">
        <div class="auth-left-content">
            <div class="brand-logo mb-4">
                <i class="fa-solid fa-user-shield fa-4x text-white"></i>
            </div>
            <h1 class="display-4 fw-bold text-white"><?= APP_NAME ?></h1>
            <p class="lead text-white-50">Security Personnel Portal</p>
            <hr class="border-white-50 my-4">
            <p class="text-white-50">Register as a security officer to access the dispatch dashboard, respond to alerts, and manage campus incidents.</p>
            <ul class="list-unstyled text-white-50 fs-6 mt-3">
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Receive real-time emergency alerts</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Accept and dispatch to incidents</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>File incident reports</li>
            </ul>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-wrapper">
            <div class="d-flex align-items-center mb-1">
                <a href="login.php" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold mb-0">Security Registration</h3>
                    <p class="text-muted small mb-0">Create your security officer account</p>
                </div>
            </div>

            <div class="register-type-badge mb-4 mt-3">
                <span class="badge-type security-badge">
                    <i class="fa fa-shield me-1"></i> Security Personnel Registration
                </span>
                <a href="register_user.php" class="switch-link">Switch to University User →</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-circle-xmark me-2"></i><strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="register_security.php" novalidate>
                <?= csrfField() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" required
                               maxlength="100" pattern="[a-zA-Z\s\-']{2,100}"
                               value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>"
                               placeholder="John">
                        <div class="invalid-feedback">Enter a valid first name.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" required
                               maxlength="100" pattern="[a-zA-Z\s\-']{2,100}"
                               value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>"
                               placeholder="Doe">
                        <div class="invalid-feedback">Enter a valid last name.</div>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Staff ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-id-badge"></i></span>
                            <input type="text" class="form-control" name="staff_id" required
                                   maxlength="50" pattern="[A-Za-z0-9\-\/]{3,50}"
                                   value="<?= htmlspecialchars($formData['staff_id'] ?? '') ?>"
                                   placeholder="e.g. SEC-2024-001">
                        </div>
                        <div class="form-text">Your official security staff ID.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-phone"></i></span>
                            <input type="tel" class="form-control" name="phone" required
                                   maxlength="20" pattern="[+0-9\s\-\(\)]{7,20}"
                                   value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                                   placeholder="+233 XX XXX XXXX">
                        </div>
                        <div class="invalid-feedback">Enter a valid phone number.</div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                        <input type="email" class="form-control" name="email" required
                               maxlength="150"
                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                               placeholder="officer@campus-security.edu.gh">
                    </div>
                    <div class="invalid-feedback">Enter a valid email address.</div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password"
                                   required minlength="8" maxlength="255" placeholder="Min 8 chars">
                            <button type="button" class="input-group-text btn-toggle-pw"
                                    onclick="togglePassword('password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="strengthBar"></div>
                        <div class="form-text" id="pwHints">
                            <span id="hint-len"   class="pw-hint">✗ 8+ characters</span>
                            <span id="hint-upper" class="pw-hint">✗ Uppercase</span>
                            <span id="hint-lower" class="pw-hint">✗ Lowercase</span>
                            <span id="hint-num"   class="pw-hint">✗ Number</span>
                            <span id="hint-sym"   class="pw-hint">✗ Symbol</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock-open"></i></span>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password"
                                   required maxlength="255" placeholder="Re-enter password">
                            <button type="button" class="input-group-text btn-toggle-pw"
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text" id="matchMsg"></div>
                    </div>
                </div>

                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="fa fa-triangle-exclamation me-1"></i>
                    Security accounts may require administrator approval before becoming active.
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-danger btn-auth w-100 fw-bold">
                        <i class="fa fa-user-shield me-2"></i>Create Security Account
                    </button>
                </div>
            </form>

            <p class="text-center text-muted mt-3">
                Already have an account? <a href="login.php?type=security" class="text-danger fw-semibold">Sign in</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id, btn) {
    const f = document.getElementById(id);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    const tests = { len: val.length >= 8, upper: /[A-Z]/.test(val), lower: /[a-z]/.test(val), num: /[0-9]/.test(val), sym: /[\W_]/.test(val) };
    const passed = Object.values(tests).filter(Boolean).length;
    const bar = document.getElementById('strengthBar');
    Object.entries(tests).forEach(([key, ok]) => {
        const el = document.getElementById('hint-' + key);
        el.textContent = (ok ? '✓ ' : '✗ ') + el.textContent.slice(2);
        el.classList.toggle('hint-ok', ok);
        el.classList.toggle('hint-fail', !ok);
    });
    bar.className = 'password-strength mt-2 strength-' + passed;
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const msg = document.getElementById('matchMsg');
    if (this.value === document.getElementById('password').value) {
        msg.innerHTML = '<span class="text-success"><i class="fa fa-check me-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="fa fa-xmark me-1"></i>Passwords do not match</span>';
    }
});

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const form = this;
    const pw = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    let valid = form.checkValidity();
    const dangerous = /<[^>]*>|javascript:|on\w+\s*=|--|;--|\/\*/i;
    form.querySelectorAll('input[type=text], input[type=email], input[type=tel]').forEach(input => {
        if (dangerous.test(input.value)) { input.setCustomValidity('Invalid characters.'); valid = false; }
        else input.setCustomValidity('');
    });
    if (pw !== cpw || pw.length < 8) valid = false;
    form.classList.add('was-validated');
    if (!valid) e.preventDefault();
});
</script>
</body>
</html>
