<?php
/**
 * TTS Audio File Server
 */

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    exit('Missing file parameter');
}

// Sanitize filename to prevent directory traversal
$file = basename($file);
$audioPath = __DIR__ . '/../tts-cache/' . $file;

if (!file_exists($audioPath)) {
    http_response_code(404);
    exit('Audio file not found');
}

// Serve the MP3
header('Content-Type: audio/mpeg');
header('Content-Length: ' . filesize($audioPath));
header('Cache-Control: public, max-age=86400');
readfile($audioPath);
