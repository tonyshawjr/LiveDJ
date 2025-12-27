<?php
/**
 * Spotify OAuth Callback Handler
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/spotify.php';

// Check for error from Spotify
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    die("Spotify authorization failed: $error");
}

// Check for authorization code
if (!isset($_GET['code'])) {
    die("No authorization code received");
}

$code = $_GET['code'];

// Exchange the code for tokens
$success = exchangeSpotifyCode($code);

if ($success) {
    // Redirect to admin with success message
    header('Location: ' . SITE_URL . '/admin/dashboard.php?spotify=connected');
    exit;
} else {
    die("Failed to exchange authorization code. Check your Spotify credentials.");
}
