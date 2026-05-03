<?php
require_once 'config.php';

// Verify it's a legitimate logout (must be logged in)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Optionally update duty status if security officer
if (isSecurityPersonnel()) {
    $secId = $_SESSION['user_id'];
    $stmt  = $conn->prepare(
        "UPDATE security_personnel SET duty_status = 'OFF_DUTY' WHERE security_id = ?"
    );
    $stmt->bind_param("s", $secId);
    $stmt->execute();
    $stmt->close();
}

// Destroy session completely
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_start();
setFlash('success', 'You have been logged out successfully.');

header('Location: login.php');
exit();
?>
