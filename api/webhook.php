<?php
/**
 * Plex Webhook Handler
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify webhook token
$token = $_GET['token'] ?? '';
$storedToken = getSetting('webhook_token');

if (empty($storedToken) || $token !== $storedToken) {
    http_response_code(403);
    exit('Invalid token');
}

// Parse Plex webhook payload
$payload = $_POST['payload'] ?? '';
if (empty($payload)) {
    http_response_code(400);
    exit('Missing payload');
}

$data = json_decode($payload, true);
if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Only process music play events
$event = $data['event'] ?? '';
$type = $data['Metadata']['type'] ?? '';

if ($event !== 'media.play' || $type !== 'track') {
    echo json_encode(['status' => 'ignored', 'event' => $event, 'type' => $type]);
    exit;
}

$meta = $data['Metadata'];

// Extract track info
$trackInfo = [
    'track' => $meta['title'] ?? '',
    'artist' => $meta['grandparentTitle'] ?? $meta['originalTitle'] ?? '',
    'album' => $meta['parentTitle'] ?? '',
    'year' => $meta['year'] ?? '',
    'thumb' => !empty($meta['thumb']) ? 'api/thumb.php?path=' . urlencode($meta['thumb']) : '',
    'artist_thumb' => !empty($meta['grandparentThumb']) ? 'api/thumb.php?path=' . urlencode($meta['grandparentThumb']) : ''
];

// Process the track
$result = processCurrentTrack($trackInfo, 'plex');

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'track' => $trackInfo['track'],
    'artist' => $trackInfo['artist']
]);
