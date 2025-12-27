<?php
/**
 * Image Proxy for Plex artwork
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    exit('Missing path parameter');
}

// Proxy the Plex image
proxyPlexImage($path);
