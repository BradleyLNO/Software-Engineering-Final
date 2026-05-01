<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . (isSecurityPersonnel() ? 'dashboard_security.php' : 'dashboard_user.php'));
    exit();
}

$error          = '';
$emailUnverified = false;
$unverifiedEmail = '';
$unverifiedType  = 'university';
$userType = $_POST['user_type'] ?? ($_GET['type'] ?? 'university');
$userType = in_array($userType, ['university', 'security']) ? $userType : 'university';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = "login_{$ip}";

    if (!checkRateLimit($rateLimitKey, 5, 300)) {
        $remaining = getRateLimitRemaining($rateLimitKey);
        $error = "Too many failed attempts. Please wait " . ceil($remaining / 60) . " minute(s) before trying again.";
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $userType   = $_POST['user_type'] ?? 'university';
        $userType   = in_array($userType, ['university', 'security']) ? $userType : 'university';

        // Basic presence check
        if (empty($identifier) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($identifier) > 150 || strlen($password) > 255) {
            $error = 'Invalid input length.';
        } else {
            if ($userType === 'university') {
                // Query university_users — match by email OR university_id
                $stmt = $conn->prepare(
                    "SELECT user_id, first_name, last_name, email, university_id, role,
                            password_hash, email_verified
                     FROM university_users
                     WHERE email = ? OR university_id = ?
                     LIMIT 1"
                );
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password_hash'])) {
                        if (!(int)$user['email_verified']) {
                            $emailUnverified = true;
                            $unverifiedEmail = $user['email'];
                            $unverifiedType  = 'university';
                        } else {
                            session_regenerate_id(true);
                            $_SESSION['user_id']        = $user['user_id'];
                            $_SESSION['user_type']      = 'university';
                            $_SESSION['first_name']     = $user['first_name'];
                            $_SESSION['last_name']      = $user['last_name'];
                            $_SESSION['email']          = $user['email'];
                            $_SESSION['university_id']  = $user['university_id'];
                            $_SESSION['role']           = $user['role'];
                            unset($_SESSION["rate_limit_{$rateLimitKey}"]);
                            header('Location: dashboard_user.php');
                            exit();
                        }
                    } else {
                        $error = 'Invalid credentials. Please try again.';
                    }
                } else {
                    // Timing-safe: still hash a dummy value
                    password_verify($password, '$2y$10$dummyhashtopreventtimingattacks00000000000000000');
                    $error = 'Invalid credentials. Please try again.';
                }
                $stmt->close();

            } else {
                // Security personnel — match by email OR staff_id
                $stmt = $conn->prepare(
                    "SELECT security_id, first_name, last_name, email, staff_id,
                            duty_status, password_hash, email_verified
                     FROM security_personnel
                     WHERE email = ? OR staff_id = ?
                     LIMIT 1"
                );
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($user['duty_status'] === 'INACTIVE') {
                        $error = 'Your account has been deactivated. Contact administration.';
                    } elseif (password_verify($password, $user['password_hash'])) {
                        if (!(int)$user['email_verified']) {
                            $emailUnverified = true;
                            $unverifiedEmail = $user['email'];
                            $unverifiedType  = 'security';
                        } else {
                            session_regenerate_id(true);
                            $_SESSION['user_id']    = $user['security_id'];
                            $_SESSION['user_type']  = 'security';
                            $_SESSION['first_name'] = $user['first_name'];
                            $_SESSION['last_name']  = $user['last_name'];
                            $_SESSION['email']      = $user['email'];
                            $_SESSION['staff_id']   = $user['staff_id'];
                            $_SESSION['duty_status']= $user['duty_status'];
                            unset($_SESSION["rate_limit_{$rateLimitKey}"]);
                            header('Location: dashboard_security.php');
                            exit();
                        }
                    } else {
                        $error = 'Invalid credentials. Please try again.';
                    }
                } else {
                    password_verify($password, '$2y$10$dummyhashtopreventtimingattacks00000000000000000');
                    $error = 'Invalid credentials. Please try again.';
                }
                $stmt->close();
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
    <title>Login — <?= APP_NAME ?></title>
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
            <ul class="list-unstyled text-white-50 fs-6">
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Instant emergency alerts</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Real-time security dispatch</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Campus-wide incident tracking</li>
                <li class="mb-2"><i class="fa fa-circle-check text-white me-2"></i>Secure two-way communication</li>
            </ul>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="auth-form-wrapper">
            <div class="text-center mb-4 d-lg-none">
                <i class="fa-solid fa-shield-halved fa-3x text-danger"></i>
                <h2 class="fw-bold text-danger mt-2"><?= APP_NAME ?></h2>
            </div>

            <h3 class="fw-bold mb-1">Welcome Back</h3>
            <p class="text-muted mb-4">Sign in to your account to continue</p>

            <!-- User type tabs -->
            <div class="user-type-tabs mb-4">
                <button type="button" class="type-tab <?= $userType === 'university' ? 'active' : '' ?>"
                        onclick="switchTab('university')">
                    <i class="fa fa-graduation-cap me-1"></i> University User
                </button>
                <button type="button" class="type-tab <?= $userType === 'security' ? 'active' : '' ?>"
                        onclick="switchTab('security')">
                    <i class="fa fa-shield me-1"></i> Security Personnel
                </button>
            </div>

            <?php if ($emailUnverified): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fa fa-envelope me-2"></i>
                    <strong>Email not verified.</strong>
                    Please check your inbox and click the verification link before signing in.<br>
                    <a href="resend_verification.php?email=<?= rawurlencode($unverifiedEmail) ?>&type=<?= $unverifiedType ?>"
                       class="alert-link fw-semibold">
                        Resend verification email &rarr;
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= sanitizeOutput($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="user_type" id="userTypeInput" value="<?= $userType ?>">

                <div class="mb-3">
                    <label for="identifier" class="form-label fw-semibold" id="identifierLabel">
                        Email or University ID
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-user" id="identifierIcon"></i></span>
                        <input type="text" class="form-control" id="identifier" name="identifier"
                               placeholder="Enter your email or ID" required autocomplete="username"
                               maxlength="150" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                    </div>
                    <div class="invalid-feedback" id="identifierError"></div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold mb-0">Password</label>
                        <a href="forgot_password.php" class="text-danger small">
                            <i class="fa fa-key me-1"></i>Forgot Password?
                        </a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required autocomplete="current-password"
                               maxlength="255">
                        <button type="button" class="input-group-text btn-toggle-pw" onclick="togglePassword('password', this)">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4"></div>

                <button type="submit" class="btn btn-danger btn-auth w-100 fw-bold">
                    <i class="fa fa-right-to-bracket me-2"></i>Sign In
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-muted" id="registerLinkText">
                    Don't have an account?
                    <a href="register_user.php" id="registerLink" class="text-danger fw-semibold">Register as University User</a>
                </p>
                <p class="text-muted small" id="securityRegisterNote" style="display:none">
                    Security personnel accounts are created by administrators.
                    <a href="register_security.php" class="text-danger">Register here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(type) {
    document.getElementById('userTypeInput').value = type;
    document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');

    const label = document.getElementById('identifierLabel');
    const icon  = document.getElementById('identifierIcon');
    const regLink = document.getElementById('registerLink');
    const regNote = document.getElementById('securityRegisterNote');
    const regText = document.getElementById('registerLinkText');

    if (type === 'security') {
        label.textContent = 'Email or Staff ID';
        icon.className = 'fa fa-id-badge';
        regText.style.display = 'none';
        regNote.style.display = 'block';
    } else {
        label.textContent = 'Email or University ID';
        icon.className = 'fa fa-user';
        regText.style.display = 'block';
        regNote.style.display = 'none';
    }
}

function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

// Front-end validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const identifier = document.getElementById('identifier').value.trim();
    const password   = document.getElementById('password').value;
    let valid = true;

    // Reset
    document.getElementById('identifier').classList.remove('is-invalid');

    // Check for potential XSS / injection patterns (front-end hint)
    const dangerous = /<[^>]*>|javascript:|on\w+\s*=|--|;--|\/\*/i;
    if (dangerous.test(identifier)) {
        document.getElementById('identifier').classList.add('is-invalid');
        document.getElementById('identifierError').textContent = 'Invalid characters detected in input.';
        valid = false;
    }

    if (!identifier) {
        document.getElementById('identifier').classList.add('is-invalid');
        document.getElementById('identifierError').textContent = 'This field is required.';
        valid = false;
    }

    if (!password) {
        document.getElementById('password').closest('.input-group').querySelector('input').classList.add('is-invalid');
        valid = false;
    }

    if (!valid) e.preventDefault();
});

// Init tab state on load
document.addEventListener('DOMContentLoaded', function() {
    const type = document.getElementById('userTypeInput').value;
    if (type === 'security') {
        document.querySelector('.type-tab:last-child').classList.add('active');
        document.querySelector('.type-tab:first-child').classList.remove('active');
        document.getElementById('registerLinkText').style.display = 'none';
        document.getElementById('securityRegisterNote').style.display = 'block';
    }
});
</script>
</body>
</html>
