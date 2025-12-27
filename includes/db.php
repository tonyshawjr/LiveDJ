<?php
/**
 * Database Connection
 */

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed. Please check your config.php settings.');
        }
    }

    return $pdo;
}

function getSetting($key, $default = '') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

function getNote($type, $key) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT note FROM notes WHERE note_type = ? AND lookup_key = ?');
    $stmt->execute([$type, $key]);
    $row = $stmt->fetch();
    return $row ? $row['note'] : '';
}

function setNote($type, $key, $note) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO notes (note_type, lookup_key, note, created_at) VALUES (?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE note = ?');
    $stmt->execute([$type, $key, $note, $note]);
}

function getCurrentState() {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM current_state ORDER BY id DESC LIMIT 1');
    return $stmt->fetch() ?: [];
}

function setCurrentState($data) {
    $pdo = getDB();

    // Clear old state
    $pdo->exec('DELETE FROM current_state');

    // Insert new state
    $stmt = $pdo->prepare('INSERT INTO current_state
        (track, artist, album, year, thumb, artist_thumb, artist_note, album_note, track_note, tts_audio_url, track_id, source, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');

    $stmt->execute([
        $data['track'] ?? '',
        $data['artist'] ?? '',
        $data['album'] ?? '',
        $data['year'] ?? '',
        $data['thumb'] ?? '',
        $data['artist_thumb'] ?? '',
        $data['artist_note'] ?? '',
        $data['album_note'] ?? '',
        $data['track_note'] ?? '',
        $data['tts_audio_url'] ?? '',
        $data['track_id'] ?? '',
        $data['source'] ?? 'unknown'
    ]);
}

function createTables() {
    $pdo = getDB();

    // Settings table
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Notes cache
    $pdo->exec('CREATE TABLE IF NOT EXISTS notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_type VARCHAR(50) NOT NULL,
        lookup_key VARCHAR(500) NOT NULL,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_note (note_type, lookup_key(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Current playback state
    $pdo->exec('CREATE TABLE IF NOT EXISTS current_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        track VARCHAR(500),
        artist VARCHAR(500),
        album VARCHAR(500),
        year VARCHAR(10),
        thumb TEXT,
        artist_thumb TEXT,
        artist_note TEXT,
        album_note TEXT,
        track_note TEXT,
        tts_audio_url VARCHAR(500),
        track_id VARCHAR(100),
        source VARCHAR(50) DEFAULT "unknown",
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Spotify tokens (for OAuth)
    $pdo->exec('CREATE TABLE IF NOT EXISTS spotify_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        access_token TEXT,
        refresh_token TEXT,
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
