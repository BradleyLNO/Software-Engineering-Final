<?php
require_once 'config.php';
requireLogin();

$isUser     = isUniversityUser();
$isSecurity = isSecurityPersonnel();
$userId     = $_SESSION['user_id'];
$errors     = [];

// ─── Fetch current data ───────────────────────────────────────────────────────
if ($isUser) {
    $stmt = $conn->prepare("SELECT * FROM university_users WHERE user_id=?");
} else {
    $stmt = $conn->prepare("SELECT * FROM security_personnel WHERE security_id=?");
}
$stmt->bind_param("s", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Handle Updates ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = sanitizeStrict($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $firstName = sanitizeStrict($_POST['first_name'] ?? '');
        $lastName  = sanitizeStrict($_POST['last_name']  ?? '');
        $phone     = sanitizeStrict($_POST['phone']      ?? '');

        if (!preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $firstName)) $errors[] = "Invalid first name.";
        if (!preg_match('/^[a-zA-Z\s\-\']{2,100}$/', $lastName))  $errors[] = "Invalid last name.";
        if (!preg_match('/^[+0-9\s\-\(\)]{7,20}$/', $phone))       $errors[] = "Invalid phone number.";

        if (empty($errors)) {
            if ($isUser) {
                $s = $conn->prepare("UPDATE university_users SET first_name=?, last_name=?, phone=? WHERE user_id=?");
            } else {
                $s = $conn->prepare("UPDATE security_personnel SET first_name=?, last_name=?, phone=? WHERE security_id=?");
            }
            $s->bind_param("ssss", $firstName, $lastName, $phone, $userId);
            if ($s->execute()) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name']  = $lastName;
                setFlash('success', 'Profile updated successfully.');
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit();
            } else {
                $errors[] = "Update failed. Please try again.";
            }
            $s->close();
        }

    } elseif ($action === 'change_password') {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_new_password'] ?? '';

        if (!password_verify($currentPw, $userData['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        }

        $pwErrors = validatePassword($newPw);
        $errors   = array_merge($errors, $pwErrors);

        if ($newPw !== $confirmPw) $errors[] = "New passwords do not match.";

        if (empty($errors)) {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            if ($isUser) {
                $s = $conn->prepare("UPDATE university_users SET password_hash=? WHERE user_id=?");
            } else {
                $s = $conn->prepare("UPDATE security_personnel SET password_hash=? WHERE security_id=?");
            }
            $s->bind_param("ss", $hash, $userId);
            if ($s->execute()) {
                setFlash('success', 'Password changed successfully. Please log in again.');
                session_destroy();
                header('Location: login.php');
                exit();
            } else {
                $errors[] = "Password change failed. Please try again.";
            }
            $s->close();
        }
    }
    // Re-fetch updated data
    if ($isUser) {
        $stmt = $conn->prepare("SELECT * FROM university_users WHERE user_id=?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM security_personnel WHERE security_id=?");
    }
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = 'Settings';
$activeNav = 'settings';
include 'partials/layout_start.php';
?>

<div class="row g-3 justify-content-center">
<div class="col-lg-8">

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <strong>Please fix:</strong>
        <ul class="mb-0 mt-1 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Profile Card -->
<div class="content-card mb-3">
    <div class="card-header-cs">
        <h5><i class="fa fa-user text-danger me-2"></i>Profile Information</h5>
    </div>
    <div class="card-body-cs">
        <form method="POST" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">First Name</label>
                    <input type="text" class="form-control" name="first_name" required
                           value="<?= htmlspecialchars($userData['first_name'] ?? '') ?>"
                           pattern="[a-zA-Z\s\-']{2,100}" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Last Name</label>
                    <input type="text" class="form-control" name="last_name" required
                           value="<?= htmlspecialchars($userData['last_name'] ?? '') ?>"
                           pattern="[a-zA-Z\s\-']{2,100}" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" disabled>
                    <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="tel" class="form-control" name="phone"
                           value="<?= htmlspecialchars($userData['phone'] ?? '') ?>"
                           maxlength="20" pattern="[+0-9\s\-\(\)]{7,20}">
                </div>
                <?php if ($isUser): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">University ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($userData['university_id'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Role</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($userData['role'] ?? '')) ?>" disabled>
                </div>
                <?php else: ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Staff ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($userData['staff_id'] ?? '') ?>" disabled>
                </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-danger">
                    <i class="fa fa-save me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Password Card -->
<div class="content-card">
    <div class="card-header-cs">
        <h5><i class="fa fa-lock text-danger me-2"></i>Change Password</h5>
    </div>
    <div class="card-body-cs">
        <form method="POST" id="pwForm" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
                <label class="form-label fw-semibold">Current Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="current_password" id="curPw" required maxlength="255">
                    <button type="button" class="input-group-text btn-toggle-pw" onclick="togglePassword('curPw', this)"><i class="fa fa-eye"></i></button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="new_password" id="newPw" required maxlength="255" minlength="8">
                        <button type="button" class="input-group-text btn-toggle-pw" onclick="togglePassword('newPw', this)"><i class="fa fa-eye"></i></button>
                    </div>
                    <div class="password-strength mt-2" id="strengthBar2"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="confirm_new_password" id="confNewPw" required maxlength="255">
                        <button type="button" class="input-group-text btn-toggle-pw" onclick="togglePassword('confNewPw', this)"><i class="fa fa-eye"></i></button>
                    </div>
                    <div class="form-text" id="pwMatch2"></div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fa fa-key me-1"></i>Change Password
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

<?php include 'partials/layout_end.php'; ?>
<script>
function togglePassword(id, btn) {
    const f = document.getElementById(id);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}
document.getElementById('newPw')?.addEventListener('input', function() {
    const v = this.value;
    const n = [v.length>=8,/[A-Z]/.test(v),/[a-z]/.test(v),/[0-9]/.test(v),/[\W_]/.test(v)].filter(Boolean).length;
    document.getElementById('strengthBar2').className = 'password-strength mt-2 strength-' + n;
});
document.getElementById('confNewPw')?.addEventListener('input', function() {
    const m = document.getElementById('pwMatch2');
    m.innerHTML = this.value === document.getElementById('newPw').value
        ? '<span class="text-success"><i class="fa fa-check me-1"></i>Match</span>'
        : '<span class="text-danger"><i class="fa fa-xmark me-1"></i>No match</span>';
});
</script>
