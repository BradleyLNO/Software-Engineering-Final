<?php
error_reporting(E_ALL);
// ─── Security Headers ─────────────────────────────────────────────────────────
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:;");

// ─── Session Configuration ─────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Database Configuration ────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'schooldb');

// ─── Application Configuration ────────────────────────────────────────────────
define('APP_NAME', 'CampusSafe');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/Software-Engineering-Final/MainFolder/campus_safety_system'); // Update for production

// ─── SMTP / Email Configuration ────────────────────────────────────────────────
// Fill in your credentials here before using email features.
// For Gmail: enable 2FA and generate an App Password at myaccount.google.com/apppasswords
// PORT 587 = STARTTLS (recommended) | 465 = SSL/TLS
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'security.campus5689@gmail.com');   // ← replace with your address
define('SMTP_PASS',      'accwfumlaefoohvy');       // ← replace with your app password
define('SMTP_FROM_NAME', APP_NAME . ' System');

// Campus locations for the dropdown (map feature placeholder)
define('CAMPUS_LOCATIONS', [
    ''          => '-- Select Campus Location --',
    'main_gate' => 'Main Gate / Entrance',
    'admin_block'=> 'Administration Block',
    'library'   => 'Main Library',
    'sci_block' => 'Science & Technology Block',
    'arts_block' => 'Arts & Humanities Block',
    'eng_block' => 'Engineering Block',
    'med_block' => 'Medical / Health Centre',
    'cafeteria' => 'Main Cafeteria / Canteen',
    'sports_field'=> 'Sports Field & Gymnasium',
    'parking_a' => 'Parking Lot A (North)',
    'parking_b' => 'Parking Lot B (South)',
    'hostel_male'=> 'Male Hostel Complex',
    'hostel_female'=>'Female Hostel Complex',
    'ict_centre'=> 'ICT / Computer Centre',
    'chapel'    => 'Chapel / Prayer Ground',
    'mosque'    => 'Mosque',
    'bus_terminal'=> 'Bus Terminal / Transport Bay',
    'workshop'  => 'Technical Workshop Area',
    'other'     => 'Other (Describe Below)',
]);

// Emergency types
define('EMERGENCY_TYPES', [
    'FIRE'          => '🔥 Fire',
    'MEDICAL'       => '🏥 Medical Emergency',
    'SECURITY'      => '🚨 Security Threat',
    'THEFT'         => '🦹 Theft / Robbery',
    'ACCIDENT'      => '🚗 Accident / Injury',
    'HARASSMENT'    => '⚠️ Harassment / Assault',
    'VANDALISM'     => '🪛 Vandalism',
    'SUSPICIOUS'    => '👁️ Suspicious Activity',
    'UTILITY'       => '⚡ Utility Failure (Power/Water)',
    'OTHER'         => '📋 Other Emergency',
]);

// ─── Database Connection ───────────────────────────────────────────────────────
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        die(json_encode(['error' => 'Database unavailable. Please try again later.']));
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("DB Exception: " . $e->getMessage());
    die("System error. Please contact support.");
}

// ─── CSRF Token Helpers ────────────────────────────────────────────────────────
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die("Invalid request. CSRF token mismatch. Please go back and try again.");
    }
}

// ─── Secure Token Generator ────────────────────────────────────────────────────
function generateSecureToken(): string {
    return bin2hex(random_bytes(32)); // 64-character hex string
}

// ─── UUID Generator ────────────────────────────────────────────────────────────
function generateUUID(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ─── Authentication Helpers ────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['user_type']);
}

function isUniversityUser(): bool {
    return isLoggedIn() && $_SESSION['user_type'] === 'university';
}

function isSecurityPersonnel(): bool {
    return isLoggedIn() && $_SESSION['user_type'] === 'security';
}

function requireLogin(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        // Prevent header manipulation bypass
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit();
    }
}

function requireUniversityUser(): void {
    requireLogin();
    if (!isUniversityUser()) {
        header("Location: dashboard_security.php");
        exit();
    }
}

function requireSecurityPersonnel(): void {
    requireLogin();
    if (!isSecurityPersonnel()) {
        header("Location: dashboard_user.php");
        exit();
    }
}

// ─── Input Sanitization ────────────────────────────────────────────────────────
function sanitize(string $data): string {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

function sanitizeOutput(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Strip any HTML/script tags from input
function sanitizeStrict(string $data): string {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ─── Password Validation ───────────────────────────────────────────────────────
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#\$%^&*).";
    }
    return $errors;
}

// ─── Rate Limiting (session-based) ────────────────────────────────────────────
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $sessionKey = "rate_limit_{$key}";
    $now = time();

    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
    }

    $data = &$_SESSION[$sessionKey];
    if ($now - $data['window_start'] > $windowSeconds) {
        $data = ['count' => 0, 'window_start' => $now];
    }

    $data['count']++;
    return $data['count'] <= $maxAttempts;
}

function getRateLimitRemaining(string $key, int $windowSeconds = 300): int {
    $sessionKey = "rate_limit_{$key}";
    if (!isset($_SESSION[$sessionKey])) return $windowSeconds;
    $elapsed = time() - $_SESSION[$sessionKey]['window_start'];
    return max(0, $windowSeconds - $elapsed);
}

// ─── Flash Messages ────────────────────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $type = $flash['type']; // success | danger | warning | info
    $msg  = sanitizeOutput($flash['message']);
    return "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                {$msg}
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
            </div>";
}
?>
