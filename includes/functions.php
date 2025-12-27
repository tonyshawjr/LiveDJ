<?php
/**
 * Helper Functions for LiveDJ
 */

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function generateWebhookToken() {
    return bin2hex(random_bytes(16));
}

/**
 * Call AI API (supports OpenAI, Groq, Gemini)
 */
function callAI($prompt) {
    $provider = defined('AI_PROVIDER') ? AI_PROVIDER : 'gemini';

    // Get the API key for the selected provider
    switch ($provider) {
        case 'openai':
            $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
            break;
        case 'gemini':
            $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
            break;
        case 'groq':
            $apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
            break;
        default:
            $apiKey = '';
    }

    if (empty($apiKey)) {
        return '';
    }

    switch ($provider) {
        case 'groq':
            return callGroq($prompt, $apiKey);
        case 'gemini':
            return callGemini($prompt, $apiKey);
        case 'openai':
        default:
            return callOpenAI($prompt, $apiKey);
    }
}

/**
 * OpenAI API
 */
function callOpenAI($prompt, $apiKey = null) {
    if (!$apiKey) $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if (empty($apiKey)) return '';

    $model = defined('AI_MODEL') ? AI_MODEL : 'gpt-4o-mini';

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 200
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] OpenAI API error: ' . $response);
        return '';
    }

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

/**
 * Groq API (OpenAI-compatible)
 */
function callGroq($prompt, $apiKey) {
    $model = defined('AI_MODEL') ? AI_MODEL : 'llama-3.3-70b-versatile';

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 200
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] Groq API error: ' . $response);
        return '';
    }

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

/**
 * Google Gemini API
 */
function callGemini($prompt, $apiKey) {
    $model = defined('AI_MODEL') ? AI_MODEL : 'gemini-2.5-flash-lite';

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 200
            ]
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] Gemini API error (HTTP ' . $httpCode . '): ' . $response);
        return '';
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($text)) {
        error_log('[LiveDJ] Gemini returned empty text. Response: ' . $response);
    }

    return trim($text);
}

function generateArtistNote($artist) {
    return callAI(
        "Write 2-3 sentences about the musician {$artist}. " .
        "Include: what genre/style they play, what era they're from, and one interesting fact about their career or sound. " .
        "Write casually like a knowledgeable friend. No hype words. No emojis. No quotes."
    );
}

function generateAlbumNote($artist, $album, $year = '') {
    $yearText = $year ? " released in {$year}" : "";
    return callAI(
        "Write 2-3 sentences about the album \"{$album}\" by {$artist}{$yearText}. " .
        "Mention something notable: the studio, the musicians, the sound, or its reception. " .
        "Write casually. No hype words. No emojis. No quotes."
    );
}

function generateTrackNote($artist, $album, $track, $year = '') {
    $yearText = $year ? " ({$year})" : "";
    return callAI(
        "Does the song \"{$track}\" by {$artist} from \"{$album}\"{$yearText} have any interesting backstory? " .
        "If yes, write 1-2 sentences about it. If nothing notable, just respond: SKIP " .
        "No hype. No emojis. No quotes."
    );
}

function generateDJAnnouncement($artist, $track, $album, $year = '') {
    $yearText = $year ? " from {$year}" : "";
    $djVoice = getSetting('dj_voice', 'free');

    if ($djVoice === 'groq') {
        // Groq TTS has 200 char limit - keep it very short
        return callAI(
            "Write a brief radio intro (under 180 characters) for \"{$track}\" by {$artist}{$yearText}. " .
            "Just introduce the song. Match the energy of the genre. " .
            "NEVER mention time of day, evening, night, or morning. No exclamation marks. No quotes."
        );
    } else {
        // OpenAI or other - no limit, give a nice full intro
        return callAI(
            "Write a 2-3 sentence radio intro for \"{$track}\" by {$artist} from \"{$album}\"{$yearText}. " .
            "Share an interesting fact about the artist, song, or album. " .
            "Match your energy to the genre - mellow for jazz, upbeat for rock, smooth for R&B, etc. " .
            "NEVER mention time of day, evening, night, settling in, or morning. Just introduce the song. " .
            "Don't address the listener's feelings or life. This will be spoken aloud. " .
            "No exclamation marks. No quotes."
        );
    }
}

function buildDJAnnouncement($info) {
    $artist = $info['artist'] ?? '';
    $track = $info['track'] ?? '';
    $album = $info['album'] ?? '';

    // Simple fallback if no AI
    $intro = "This is {$track} by {$artist}";
    if (!empty($album)) {
        $intro .= ", from the album {$album}";
    }
    return $intro . ".";
}

function generateTTSAudio($text, $cacheKey) {
    // Check DJ voice setting
    $djVoice = getSetting('dj_voice', 'free');

    // If off or free (browser), skip audio generation
    if ($djVoice === 'off' || $djVoice === 'free') {
        return null;
    }

    $groqKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
    $openaiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

    // Determine provider based on setting
    if ($djVoice === 'groq') {
        $useGroq = true;
        $apiKey = $groqKey;
    } else {
        $useGroq = false;
        $apiKey = $openaiKey;
    }

    if (empty($apiKey) || empty($text)) {
        return null;
    }

    // Clean text to prevent yelling - remove exclamation marks, normalize caps
    $text = str_replace('!', '.', $text);
    $text = preg_replace('/([A-Z]{2,})/', ucfirst(strtolower('$1')), $text); // Fix ALL CAPS words
    $text = preg_replace('/\.+/', '.', $text); // Multiple periods to single
    $text = trim($text);

    // Create TTS cache directory
    $cacheDir = __DIR__ . '/../tts-cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    // Auto-cleanup: delete files older than 7 days
    $maxAge = 7 * 24 * 60 * 60; // 7 days in seconds
    foreach (glob($cacheDir . '/*.{mp3,wav}', GLOB_BRACE) as $file) {
        if (filemtime($file) < time() - $maxAge) {
            unlink($file);
        }
    }

    // Create safe filename (wav for Groq, mp3 for OpenAI)
    $ext = $useGroq ? 'wav' : 'mp3';
    $safeFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $cacheKey);
    $safeFilename = substr($safeFilename, 0, 100) . '.' . $ext;
    $audioPath = $cacheDir . '/' . $safeFilename;

    // Check if already cached
    if (file_exists($audioPath)) {
        return 'api/tts.php?file=' . urlencode($safeFilename);
    }

    if ($useGroq) {
        // Groq TTS has 200 char limit - truncate to last complete sentence
        if (strlen($text) > 195) {
            $truncated = substr($text, 0, 195);
            // Find last sentence end
            $lastPeriod = strrpos($truncated, '.');
            if ($lastPeriod !== false && $lastPeriod > 50) {
                $text = substr($truncated, 0, $lastPeriod + 1);
            } else {
                $text = $truncated . '...';
            }
        }

        // Use Groq TTS (free)
        $ch = curl_init('https://api.groq.com/openai/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'canopylabs/orpheus-v1-english',
                'voice' => 'hannah',
                'input' => $text,
                'response_format' => 'wav'
            ]),
            CURLOPT_TIMEOUT => 30
        ]);
        $provider = 'Groq';
    } else {
        // Use OpenAI TTS
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'tts-1',
                'voice' => 'coral',
                'input' => $text,
                'response_format' => 'mp3'
            ]),
            CURLOPT_TIMEOUT => 30
        ]);
        $provider = 'OpenAI';
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[LiveDJ] TTS generation error (' . $provider . '): HTTP ' . $httpCode . ' - ' . substr($response, 0, 200));
        return null;
    }

    // Save to cache
    file_put_contents($audioPath, $response);
    error_log('[LiveDJ] Generated TTS audio via ' . $provider . ': ' . $safeFilename);

    return 'api/tts.php?file=' . urlencode($safeFilename);
}

function proxyPlexImage($path) {
    $plexToken = getSetting('plex_token');
    $plexUrl = getSetting('plex_url', 'http://127.0.0.1:32400');

    if (empty($plexToken) || empty($path)) {
        return '';
    }

    $url = rtrim($plexUrl, '/') . $path . '?X-Plex-Token=' . $plexToken;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $data = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $data) {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        echo $data;
        exit;
    }

    http_response_code(404);
    exit;
}

/**
 * Process currently playing track (works for both Plex and Spotify)
 */
function processCurrentTrack($trackInfo, $source = 'unknown') {
    if (empty($trackInfo['track']) || empty($trackInfo['artist'])) {
        return null;
    }

    $artist = $trackInfo['artist'];
    $album = $trackInfo['album'] ?? '';
    $track = $trackInfo['track'];
    $year = $trackInfo['year'] ?? '';

    // Check/generate artist note
    $artistNote = getNote('artist', $artist);
    if (empty($artistNote)) {
        $artistNote = generateArtistNote($artist);
        if (!empty($artistNote)) {
            setNote('artist', $artist, $artistNote);
        }
        usleep(500000); // 0.5s delay to avoid rate limits
    }

    // Check/generate album note
    $albumKey = "{$artist}|{$album}";
    $albumNote = getNote('album', $albumKey);
    if (empty($albumNote) && !empty($album)) {
        $albumNote = generateAlbumNote($artist, $album, $year);
        if (!empty($albumNote)) {
            setNote('album', $albumKey, $albumNote);
        }
        usleep(500000); // 0.5s delay to avoid rate limits
    }

    // Check/generate track note
    $trackKey = "{$artist}|{$album}|{$track}";
    $trackNote = getNote('track', $trackKey);
    if (empty($trackNote)) {
        $generated = generateTrackNote($artist, $album, $track, $year);
        if (!empty($generated) && strtoupper(trim($generated)) !== 'SKIP') {
            $trackNote = $generated;
            setNote('track', $trackKey, $trackNote);
        }
    }

    // Generate unique DJ announcement (separate from slide notes)
    $djKey = "dj|{$artist}|{$album}|{$track}";
    $djAnnouncement = getNote('dj', $djKey);
    if (empty($djAnnouncement)) {
        $djAnnouncement = generateDJAnnouncement($artist, $track, $album, $year);
        if (!empty($djAnnouncement)) {
            setNote('dj', $djKey, $djAnnouncement);
        } else {
            // Fallback
            $djAnnouncement = buildDJAnnouncement(['artist' => $artist, 'track' => $track, 'album' => $album]);
        }
        usleep(500000); // 0.5s delay
    }

    // Build state data
    $stateData = [
        'track' => $track,
        'artist' => $artist,
        'album' => $album,
        'year' => $year,
        'thumb' => $trackInfo['thumb'] ?? '',
        'artist_thumb' => $trackInfo['artist_thumb'] ?? '',
        'artist_note' => $artistNote,
        'album_note' => $albumNote,
        'track_note' => $trackNote,
        'dj_announcement' => $djAnnouncement,
        'track_id' => $trackInfo['track_id'] ?? '',
        'source' => $source
    ];

    // Generate TTS audio
    error_log('[LiveDJ] Generating TTS for: ' . $track . ' by ' . $artist);
    error_log('[LiveDJ] DJ announcement: ' . substr($djAnnouncement, 0, 100));
    $ttsUrl = generateTTSAudio($djAnnouncement, $trackKey);
    error_log('[LiveDJ] TTS result: ' . ($ttsUrl ?: 'NULL'));
    if ($ttsUrl) {
        $stateData['tts_audio_url'] = $ttsUrl;
    }

    // Save current state
    setCurrentState($stateData);

    return $stateData;
}
