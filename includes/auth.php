<?php
/**
 * Authentication Functions
 */

session_start();

function isLoggedIn() {
    return isset($_SESSION['livedj_logged_in']) && $_SESSION['livedj_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function login($username, $password) {
    $storedUser = getSetting('admin_username');
    $storedPass = getSetting('admin_password');

    if ($username === $storedUser && password_verify($password, $storedPass)) {
        $_SESSION['livedj_logged_in'] = true;
        $_SESSION['livedj_username'] = $username;
        return true;
    }

    return false;
}

function logout() {
    session_destroy();
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}
