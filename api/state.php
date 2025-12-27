<?php
/**
 * Get Current Playback State API
 * For Spotify: Also checks for track changes on each request
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$progressMs = 0;
$durationMs = 0;

// For Spotify: Check for track changes on each poll
if (defined('PLATFORM') && PLATFORM === 'spotify') {
    require_once __DIR__ . '/../includes/spotify.php';
    require_once __DIR__ . '/../includes/functions.php';

    if (isSpotifyConnected()) {
        $playing = getSpotifyCurrentlyPlaying();

        if ($playing && $playing['playing']) {
            // Get progress info
            $progressMs = $playing['progress_ms'] ?? 0;
            $durationMs = $playing['duration_ms'] ?? 0;

            // Check if track changed
            $currentState = getCurrentState();
            $currentTrackId = $currentState['track_id'] ?? '';

            if ($currentTrackId !== ($playing['track_id'] ?? '')) {
                // New track - process it
                $artistThumb = '';
                if (!empty($playing['artist'])) {
                    $artistThumb = getSpotifyArtistImage($playing['artist']);
                }

                $trackInfo = [
                    'track' => $playing['track'],
                    'artist' => $playing['artist'],
                    'album' => $playing['album'],
                    'year' => $playing['year'],
                    'thumb' => $playing['thumb'],
                    'artist_thumb' => $artistThumb,
                    'track_id' => $playing['track_id']
                ];

                processCurrentTrack($trackInfo, 'spotify');
            }
        }
    }
}

$state = getCurrentState();

if (empty($state)) {
    echo json_encode([
        'playing' => false,
        'track' => '',
        'artist' => '',
        'album' => '',
        'year' => '',
        'thumb' => '',
        'artist_thumb' => '',
        'artist_note' => '',
        'album_note' => '',
        'track_note' => '',
        'tts_audio_url' => '',
        'source' => ''
    ]);
    exit;
}

echo json_encode([
    'playing' => true,
    'track' => $state['track'] ?? '',
    'artist' => $state['artist'] ?? '',
    'album' => $state['album'] ?? '',
    'year' => $state['year'] ?? '',
    'thumb' => $state['thumb'] ?? '',
    'artist_thumb' => $state['artist_thumb'] ?? '',
    'artist_note' => $state['artist_note'] ?? '',
    'album_note' => $state['album_note'] ?? '',
    'track_note' => $state['track_note'] ?? '',
    'tts_audio_url' => $state['tts_audio_url'] ?? '',
    'source' => $state['source'] ?? '',
    'progress_ms' => $progressMs,
    'duration_ms' => $durationMs
]);
