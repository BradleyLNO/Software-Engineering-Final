<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$errors  = [];
$success = false;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Rate limit registrations
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("register_user_{$ip}", 5, 300)) {
        $errors[] = "Too many registration attempts. Please wait 5 minutes.";
    } else {
        // Collect & sanitize
        $formData['first_name']    = sanitizeStrict($_POST['first_name'] ?? '');
        $formData['last_name']     = sanitizeStrict($_POST['last_name'] ?? '');
        $formData['university_id'] = sanitizeStrict($_POST['university_id'] ?? '');
        $formData['email']         = sanitizeStrict($_POST['email'] ?? '');
        $formData['phone']         = sanitizeStrict($_POST['phone'] ?? '');
        $formData['role']          = sanitizeStrict($_POST['role'] ?? '');
        $password                  = $_POST['password'] ?? '';
        $confirmPassword           = $_POST['confirm_password'] ?? '';

        // ── Validate first name
        if (empty($formData['first_name']) || strlen($formData['first_name']) < 2) {
            $errors[] = "First name must be at least 2 characters.";
        } elseif (!preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $formData['first_name'])) {
            $errors[] = "First name contains invalid characters.";
        }

        // ── Validate last name
        if (empty($formData['last_name']) || strlen($formData['last_name']) < 2) {
            $errors[] = "Last name must be at least 2 characters.";
        } elseif (!preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $formData['last_name'])) {
            $errors[] = "Last name contains invalid characters.";
        }

        // ── Validate university ID
        if (empty($formData['university_id'])) {
            $errors[] = "University ID is required.";
        } elseif (!preg_match('/^[A-Za-z0-9\-\/]{3,50}$/', $formData['university_id'])) {
            $errors[] = "University ID must be 3–50 alphanumeric characters.";
        }

        // ── Validate email
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        } elseif (strlen($formData['email']) > 150) {
            $errors[] = "Email address is too long.";
        }

        // ── Validate phone
        if (!preg_match('/^[+0-9\s\-\(\)]{7,20}$/', $formData['phone'])) {
            $errors[] = "Please enter a valid phone number.";
        }

        // ── Validate role
        $allowedRoles = ['student', 'staff', 'faculty', 'administrator'];
        if (!in_array($formData['role'], $allowedRoles)) {
            $errors[] = "Please select a valid role.";
        }

        // ── Validate password
        $pwErrors = validatePassword($password);
        $errors = array_merge($errors, $pwErrors);

        // ── Confirm password
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        // ── Check uniqueness
        if (empty($errors)) {
            $stmt = $conn->prepare(
                "SELECT user_id FROM university_users WHERE email = ? OR university_id = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $formData['email'], $formData['university_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "An account with that email or university ID already exists.";
            }
            $stmt->close();
        }

        // ── Create account
        if (empty($errors)) {
            $uuid         = generateUUID();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $conn->prepare(
                "INSERT INTO university_users
                    (user_id, university_id, first_name, last_name, email, phone,
                     password_hash, role, email_verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->bind_param(
                "ssssssss",
                $uuid,
                $formData['university_id'],
                $formData['first_name'],
                $formData['last_name'],
                $formData['email'],
                $formData['phone'],
                $passwordHash,
                $formData['role']
            );

            if ($stmt->execute()) {
                $stmt->close();
                setFlash('success', 'Account created! You can now sign in.');
                header('Location: login.php?type=university');
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
    <title>Register — University User — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="auth-split">
    <!-- Left panel -->
    <div class="auth-left d-none d-lg-flex">
        <div class="auth-left-content">
            <div class="brand-logo mb-4">
                <i class="fa-solid fa-shield-halved fa-4x text-white"></i>
            </div>
            <h1 class="display-4 fw-bold text-white"><?= APP_NAME ?></h1>
            <p class="lead text-white-50">Campus Emergency Response System</p>
            <hr class="border-white-50 my-4">
            <p class="text-white-50">Register as a university member to gain access to emergency reporting tools and campus safety resources.</p>
            <ul class="list-unstyled text-white-50 fs-6 mt-3">
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Report incidents in seconds</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Track your alert status live</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Communicate with security</li>
            </ul>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="auth-form-wrapper">
            <div class="text-center mb-3 d-lg-none">
                <i class="fa-solid fa-shield-halved fa-2x text-danger"></i>
            </div>

            <div class="d-flex align-items-center mb-1">
                <a href="login.php" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold mb-0">Create Account</h3>
                    <p class="text-muted small mb-0">University Student / Staff / Faculty</p>
                </div>
            </div>

            <div class="register-type-badge mb-4 mt-3">
                <span class="badge-type university-badge">
                    <i class="fa fa-graduation-cap me-1"></i> University User Registration
                </span>
                <a href="register_security.php" class="switch-link">Switch to Security Personnel →</a>
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

            <form id="registerForm" method="POST" action="register_user.php" novalidate>
                <?= csrfField() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" required
                               maxlength="100" pattern="[a-zA-Z\s\-']{2,100}"
                               value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>"
                               placeholder="John">
                        <div class="invalid-feedback">Enter a valid first name (letters only).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" required
                               maxlength="100" pattern="[a-zA-Z\s\-']{2,100}"
                               value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>"
                               placeholder="Doe">
                        <div class="invalid-feedback">Enter a valid last name (letters only).</div>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">University ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-id-card"></i></span>
                            <input type="text" class="form-control" name="university_id" required
                                   maxlength="50" pattern="[A-Za-z0-9\-\/]{3,50}"
                                   value="<?= htmlspecialchars($formData['university_id'] ?? '') ?>"
                                   placeholder="e.g. UG/2021/0001">
                        </div>
                        <div class="form-text">Your official student/staff ID number.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="">Select your role</option>
                            <option value="student"       <?= ($formData['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="staff"         <?= ($formData['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="faculty"       <?= ($formData['role'] ?? '') === 'faculty' ? 'selected' : '' ?>>Faculty / Lecturer</option>
                            <option value="administrator" <?= ($formData['role'] ?? '') === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                        <div class="invalid-feedback">Please select a role.</div>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" required
                                   maxlength="150"
                                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                   placeholder="you@university.edu.gh">
                        </div>
                        <div class="invalid-feedback">Enter a valid email address.</div>
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

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password"
                                   required minlength="8" maxlength="255"
                                   placeholder="Min 8 chars">
                            <button type="button" class="input-group-text btn-toggle-pw"
                                    onclick="togglePassword('password', this)">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="strengthBar"></div>
                        <div class="form-text" id="pwHints">
                            <span id="hint-len"  class="pw-hint">✗ 8+ characters</span>
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

                <div class="mt-4">
                    <button type="submit" class="btn btn-danger btn-auth w-100 fw-bold" id="submitBtn">
                        <i class="fa fa-user-plus me-2"></i>Create Account
                    </button>
                </div>
            </form>

            <p class="text-center text-muted mt-3">
                Already have an account? <a href="login.php" class="text-danger fw-semibold">Sign in</a>
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

// Live password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
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

    const levels = ['', 'strength-1', 'strength-2', 'strength-3', 'strength-4', 'strength-5'];
    bar.className = 'password-strength mt-2 ' + (levels[passed] || '');
});

// Confirm password match
document.getElementById('confirm_password').addEventListener('input', function() {
    const msg = document.getElementById('matchMsg');
    if (this.value === document.getElementById('password').value) {
        msg.innerHTML = '<span class="text-success"><i class="fa fa-check me-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="fa fa-xmark me-1"></i>Passwords do not match</span>';
    }
});

// Front-end form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const form   = this;
    const pw     = document.getElementById('password').value;
    const cpw    = document.getElementById('confirm_password').value;
    let   valid  = form.checkValidity();

    // XSS pattern check on all text inputs
    const dangerous = /<[^>]*>|javascript:|on\w+\s*=|--|;--|\/\*/i;
    form.querySelectorAll('input[type=text], input[type=email], input[type=tel]').forEach(input => {
        if (dangerous.test(input.value)) {
            input.setCustomValidity('Invalid characters detected.');
            valid = false;
        } else {
            input.setCustomValidity('');
        }
    });

    if (pw !== cpw) { valid = false; }

    const pwChecks = [/[A-Z]/, /[a-z]/, /[0-9]/, /[\W_]/];
    if (pw.length < 8 || !pwChecks.every(r => r.test(pw))) { valid = false; }

    form.classList.add('was-validated');
    if (!valid) e.preventDefault();
});
</script>
</body>
</html>
