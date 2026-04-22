<?php
/**
 * Authentication and session management
 */

function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array(getUserRole(), $roles)) {
        redirect('index.php');
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? null;
}

function getUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isProjectManager() {
    return getUserRole() === 'project_manager';
}

function isClient() {
    return getUserRole() === 'client';
}

function isWavesSide() {
    return in_array(getUserRole(), ['admin', 'project_manager']);
}

/**
 * Login a user by setting session variables
 */
function loginUser($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
}

/**
 * Destroy the session / logout
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get full current user data from DB
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([getUserId()]);
    return $stmt->fetch();
}

/**
 * Count unread notifications for current user
 */
function getUnreadNotificationCount() {
    global $pdo;
    if (!isLoggedIn()) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([getUserId()]);
    return (int)$stmt->fetchColumn();
}
