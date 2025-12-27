<?php
/**
 * Spotify API Functions
 */

/**
 * Get the Spotify authorization URL for OAuth
 */
function getSpotifyAuthUrl() {
    $clientId = getSetting('spotify_client_id');
    $redirectUri = SITE_URL . '/api/spotify-callback.php';
    $scopes = 'user-read-currently-playing user-read-playback-state';

    $params = http_build_query([
        'client_id' => $clientId,
        'response_type' => 'code',
        'redirect_uri' => $redirectUri,
        'scope' => $scopes,
        'show_dialog' => 'true'
    ]);

    return 'https://accounts.spotify.com/authorize?' . $params;
}

/**
 * Exchange authorization code for access token
 */
function exchangeSpotifyCode($code) {
    $clientId = getSetting('spotify_client_id');
    $clientSecret = getSetting('spotify_client_secret');
    $redirectUri = SITE_URL . '/api/spotify-callback.php';

    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] Spotify token exchange failed: ' . $response);
        return false;
    }

    $data = json_decode($response, true);

    // Store tokens
    saveSpotifyTokens($data['access_token'], $data['refresh_token'], $data['expires_in']);

    return true;
}

/**
 * Refresh the Spotify access token
 */
function refreshSpotifyToken() {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT refresh_token FROM spotify_tokens ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();

    if (!$row || empty($row['refresh_token'])) {
        return false;
    }

    $clientId = getSetting('spotify_client_id');
    $clientSecret = getSetting('spotify_client_secret');

    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $row['refresh_token']
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] Spotify token refresh failed: ' . $response);
        return false;
    }

    $data = json_decode($response, true);

    // Update tokens (refresh_token may or may not be returned)
    $newRefreshToken = $data['refresh_token'] ?? $row['refresh_token'];
    saveSpotifyTokens($data['access_token'], $newRefreshToken, $data['expires_in']);

    return $data['access_token'];
}

/**
 * Save Spotify tokens to database
 */
function saveSpotifyTokens($accessToken, $refreshToken, $expiresIn) {
    $pdo = getDB();

    // Clear old tokens
    $pdo->exec('DELETE FROM spotify_tokens');

    // Insert new tokens
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn - 60); // 60 second buffer
    $stmt = $pdo->prepare('INSERT INTO spotify_tokens (access_token, refresh_token, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$accessToken, $refreshToken, $expiresAt]);
}

/**
 * Get valid Spotify access token (refreshes if needed)
 */
function getSpotifyAccessToken() {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT access_token, expires_at FROM spotify_tokens ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    // Check if expired
    if (strtotime($row['expires_at']) <= time()) {
        return refreshSpotifyToken();
    }

    return $row['access_token'];
}

/**
 * Check if Spotify is connected
 */
function isSpotifyConnected() {
    return getSpotifyAccessToken() !== null;
}

/**
 * Get currently playing track from Spotify
 */
function getSpotifyCurrentlyPlaying() {
    $accessToken = getSpotifyAccessToken();
    if (!$accessToken) {
        return null;
    }

    $ch = curl_init('https://api.spotify.com/v1/me/player/currently-playing');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 204 = nothing playing
    if ($httpCode === 204 || empty($response)) {
        return ['playing' => false];
    }

    if ($httpCode === 401) {
        // Token expired, try refresh
        $newToken = refreshSpotifyToken();
        if ($newToken) {
            return getSpotifyCurrentlyPlaying(); // Retry
        }
        return null;
    }

    if ($httpCode !== 200) {
        error_log('[LiveDJ] Spotify API error: ' . $httpCode);
        return null;
    }

    $data = json_decode($response, true);

    if (!isset($data['item']) || !$data['is_playing']) {
        return ['playing' => false];
    }

    $item = $data['item'];
    $artists = array_map(function($a) { return $a['name']; }, $item['artists'] ?? []);

    return [
        'playing' => true,
        'track' => $item['name'] ?? '',
        'artist' => implode(', ', $artists),
        'album' => $item['album']['name'] ?? '',
        'year' => substr($item['album']['release_date'] ?? '', 0, 4),
        'thumb' => $item['album']['images'][0]['url'] ?? '',
        'artist_thumb' => '', // Spotify doesn't provide this directly
        'track_id' => $item['id'] ?? '',
        'progress_ms' => $data['progress_ms'] ?? 0,
        'duration_ms' => $item['duration_ms'] ?? 0
    ];
}

/**
 * Get artist image from Spotify
 */
function getSpotifyArtistImage($artistName) {
    $accessToken = getSpotifyAccessToken();
    if (!$accessToken) {
        return '';
    }

    $ch = curl_init('https://api.spotify.com/v1/search?' . http_build_query([
        'q' => $artistName,
        'type' => 'artist',
        'limit' => 1
    ]));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return '';
    }

    $data = json_decode($response, true);
    $artists = $data['artists']['items'] ?? [];

    if (empty($artists)) {
        return '';
    }

    return $artists[0]['images'][0]['url'] ?? '';
}
