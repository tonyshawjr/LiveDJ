<?php
/**
 * Spotify Polling Service
 * Run this via cron every 5-10 seconds
 * Example cron: * * * * * php /path/to/api/spotify-poll.php
 *
 * For more frequent polling (every 5 seconds), use a wrapper script:
 * while true; do php /path/to/api/spotify-poll.php; sleep 5; done
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/spotify.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if we're configured for Spotify
if (PLATFORM !== 'spotify') {
    exit('Platform is not set to Spotify');
}

// Check if Spotify is connected
if (!isSpotifyConnected()) {
    exit('Spotify not connected');
}

// Get currently playing
$playing = getSpotifyCurrentlyPlaying();

if (!$playing || !$playing['playing']) {
    // Nothing playing - optionally clear state
    exit('Nothing playing');
}

// Get current state from database to check if track changed
$currentState = getCurrentState();
$currentTrackId = $currentState['track_id'] ?? '';

// If same track, don't reprocess
if (!empty($currentTrackId) && $currentTrackId === ($playing['track_id'] ?? '')) {
    exit('Same track');
}

// Try to get artist image
$artistThumb = '';
if (!empty($playing['artist'])) {
    $artistThumb = getSpotifyArtistImage($playing['artist']);
}

// Build track info
$trackInfo = [
    'track' => $playing['track'],
    'artist' => $playing['artist'],
    'album' => $playing['album'],
    'year' => $playing['year'],
    'thumb' => $playing['thumb'],
    'artist_thumb' => $artistThumb,
    'track_id' => $playing['track_id']
];

// Process the track (generates notes, TTS, saves state)
$result = processCurrentTrack($trackInfo, 'spotify');

if ($result) {
    echo "Processed: {$playing['artist']} - {$playing['track']}";
} else {
    echo "Failed to process track";
}
