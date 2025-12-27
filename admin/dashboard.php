<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/spotify.php';

requireLogin();

$success = '';
$error = '';

// Handle Spotify connected message
if (isset($_GET['spotify']) && $_GET['spotify'] === 'connected') {
    $success = 'Spotify connected successfully!';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {

    if (isset($_POST['save_settings'])) {
        setSetting('dj_voice', trim($_POST['dj_voice'] ?? 'free'));

        // Platform-specific settings
        if (PLATFORM === 'plex') {
            setSetting('plex_token', trim($_POST['plex_token'] ?? ''));
            setSetting('plex_url', trim($_POST['plex_url'] ?? 'http://127.0.0.1:32400'));
        } else {
            setSetting('spotify_client_id', trim($_POST['spotify_client_id'] ?? ''));
            setSetting('spotify_client_secret', trim($_POST['spotify_client_secret'] ?? ''));
        }

        // Update config.php values
        $configPath = __DIR__ . '/../config.php';
        $configContent = file_get_contents($configPath);
        $configChanged = false;

        // Update SITE_URL
        $newSiteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
        if (!empty($newSiteUrl) && $newSiteUrl !== SITE_URL) {
            $configContent = preg_replace(
                "/define\('SITE_URL',\s*'[^']*'\);/",
                "define('SITE_URL', '" . addslashes($newSiteUrl) . "');",
                $configContent
            );
            $configChanged = true;
        }

        // Update AI settings in config.php
        $newProvider = trim($_POST['ai_provider'] ?? 'gemini');
        $newModel = trim($_POST['ai_model'] ?? '');

        // Auto-set default model based on provider
        $defaultModels = [
            'gemini' => 'gemini-2.5-flash-lite',
            'groq' => 'llama-3.3-70b-versatile',
            'openai' => 'gpt-4.1-mini'
        ];
        $validModels = [
            'gemini' => ['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash'],
            'groq' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
            'openai' => ['gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-nano', 'gpt-5-mini', 'gpt-4o-mini', 'gpt-4o']
        ];
        if (empty($newModel) || !in_array($newModel, $validModels[$newProvider] ?? [])) {
            $newModel = $defaultModels[$newProvider] ?? 'gemini-2.5-flash-lite';
        }

        $newOpenaiKey = trim($_POST['openai_api_key'] ?? '');
        $newGeminiKey = trim($_POST['gemini_api_key'] ?? '');
        $newGroqKey = trim($_POST['groq_api_key'] ?? '');

        // Helper function to update or add a define
        $updateDefine = function($content, $name, $value) {
            $escaped = addslashes($value);
            if (strpos($content, $name) !== false) {
                return preg_replace(
                    "/define\('{$name}',\s*'[^']*'\);/",
                    "define('{$name}', '{$escaped}');",
                    $content
                );
            } else {
                return $content . "define('{$name}', '{$escaped}');\n";
            }
        };

        // Add comment if AI settings don't exist yet
        if (strpos($configContent, 'AI_PROVIDER') === false) {
            $configContent .= "\n// AI Settings\n";
        }

        $configContent = $updateDefine($configContent, 'AI_PROVIDER', $newProvider);
        $configContent = $updateDefine($configContent, 'AI_MODEL', $newModel);
        $configContent = $updateDefine($configContent, 'OPENAI_API_KEY', $newOpenaiKey);
        $configContent = $updateDefine($configContent, 'GEMINI_API_KEY', $newGeminiKey);
        $configContent = $updateDefine($configContent, 'GROQ_API_KEY', $newGroqKey);

        $configChanged = true;

        if ($configChanged) {
            file_put_contents($configPath, $configContent);
            $success = 'Settings saved. Config updated - please refresh the page.';
        } else {
            $success = 'Settings saved successfully.';
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $storedPass = getSetting('admin_password');

        if (!password_verify($current, $storedPass)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            setSetting('admin_password', password_hash($new, PASSWORD_DEFAULT));
            $success = 'Password changed successfully.';
        }
    }

    if (isset($_POST['regenerate_token'])) {
        setSetting('webhook_token', bin2hex(random_bytes(16)));
        $success = 'Webhook token regenerated. Update your Plex webhook settings!';
    }

    if (isset($_POST['clear_notes'])) {
        $pdo = getDB();
        $pdo->exec('DELETE FROM notes');
        $success = 'All cached notes have been cleared.';
    }

    if (isset($_POST['disconnect_spotify'])) {
        $pdo = getDB();
        $pdo->exec('DELETE FROM spotify_tokens');
        $success = 'Spotify disconnected.';
    }
}

// Get current settings
$aiProvider = defined('AI_PROVIDER') ? AI_PROVIDER : 'gemini';
$aiModel = defined('AI_MODEL') ? AI_MODEL : 'gemini-2.0-flash';
$djVoice = getSetting('dj_voice', 'free');

// Platform-specific
$plexToken = getSetting('plex_token');
$plexUrl = getSetting('plex_url', 'http://127.0.0.1:32400');
$webhookToken = getSetting('webhook_token');
$spotifyClientId = getSetting('spotify_client_id');
$spotifyClientSecret = getSetting('spotify_client_secret');
$spotifyConnected = PLATFORM === 'spotify' && isSpotifyConnected();

$webhookUrl = SITE_URL . '/api/webhook.php?token=' . $webhookToken;
$displayUrl = SITE_URL . '/display.php';

// Get notes count
$pdo = getDB();
$notesCount = $pdo->query('SELECT COUNT(*) as cnt FROM notes')->fetch()['cnt'];

// Get current state
$currentState = getCurrentState();

$isPlex = PLATFORM === 'plex';
$isSpotify = PLATFORM === 'spotify';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiveDJ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0d0d0d;
            color: #fff;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        h1 { font-size: 28px; }
        .platform-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            margin-left: 12px;
            vertical-align: middle;
        }
        .platform-plex { background: #e5a00d; color: #000; }
        .platform-spotify { background: #1db954; color: #fff; }
        .logout {
            color: #888;
            text-decoration: none;
            font-size: 14px;
        }
        .logout:hover { color: #fff; }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-success {
            background: #14532d;
            border: 1px solid #22c55e;
        }
        .alert-error {
            background: #7f1d1d;
            border: 1px solid #991b1b;
        }

        .card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #333;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #222;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
        }
        .info-value {
            font-size: 14px;
            color: #22c55e;
            word-break: break-all;
            text-align: right;
            max-width: 70%;
        }
        .info-value a {
            color: #60a5fa;
            text-decoration: none;
        }
        .info-value.warning { color: #fbbf24; }

        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        input, select {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            background: #0d0d0d;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #666;
        }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
        }

        .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #fff;
            color: #0d0d0d;
        }
        .btn-primary:hover { background: #eee; }
        .btn-danger {
            background: #7f1d1d;
            color: #fff;
        }
        .btn-danger:hover { background: #991b1b; }
        .btn-secondary {
            background: #333;
            color: #fff;
        }
        .btn-secondary:hover { background: #444; }
        .btn-spotify {
            background: #1db954;
            color: #fff;
        }
        .btn-spotify:hover { background: #1aa34a; }

        .btn-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .now-playing {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .now-playing-info h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        .now-playing-info p {
            color: #888;
            font-size: 14px;
        }
        .nothing-playing {
            color: #666;
            font-style: italic;
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .status-dot.connected { background: #22c55e; }
        .status-dot.disconnected { background: #ef4444; }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 600px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>LiveDJ
                <span class="platform-badge platform-<?php echo PLATFORM; ?>"><?php echo ucfirst(PLATFORM); ?></span>
            </h1>
            <a href="logout.php" class="logout">Sign Out</a>
        </header>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- URLs -->
        <div class="card">
            <h2>Your URLs</h2>
            <?php if ($isPlex): ?>
            <div class="info-row">
                <span class="info-label">Webhook URL</span>
                <span class="info-value"><?php echo htmlspecialchars($webhookUrl); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Display URL</span>
                <span class="info-value"><a href="<?php echo htmlspecialchars($displayUrl); ?>" target="_blank"><?php echo htmlspecialchars($displayUrl); ?></a></span>
            </div>
            <div class="info-row">
                <span class="info-label">Cached Notes</span>
                <span class="info-value"><?php echo $notesCount; ?></span>
            </div>
            <?php if ($isSpotify): ?>
            <div class="info-row">
                <span class="info-label">Spotify Status</span>
                <span class="info-value">
                    <span class="connection-status">
                        <span class="status-dot <?php echo $spotifyConnected ? 'connected' : 'disconnected'; ?>"></span>
                        <?php echo $spotifyConnected ? 'Connected' : 'Not Connected'; ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            <form method="POST" style="margin-top: 16px;">
                <?php echo csrfField(); ?>
                <div class="btn-row">
                    <?php if ($isPlex): ?>
                    <button type="submit" name="regenerate_token" class="btn btn-secondary" onclick="return confirm('This will break your existing Plex webhook. Continue?')">Regenerate Webhook Token</button>
                    <?php endif; ?>
                    <?php if ($isSpotify): ?>
                        <?php if ($spotifyConnected): ?>
                        <button type="submit" name="disconnect_spotify" class="btn btn-secondary" onclick="return confirm('Disconnect from Spotify?')">Disconnect Spotify</button>
                        <?php elseif (!empty($spotifyClientId) && !empty($spotifyClientSecret)): ?>
                        <a href="<?php echo htmlspecialchars(getSpotifyAuthUrl()); ?>" class="btn btn-spotify">Connect Spotify</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button type="submit" name="clear_notes" class="btn btn-danger" onclick="return confirm('Delete all cached notes?')">Clear Notes Cache</button>
                </div>
            </form>
        </div>

        <!-- Now Playing -->
        <div class="card">
            <h2>Now Playing</h2>
            <?php if (!empty($currentState['track'])): ?>
                <div class="now-playing">
                    <div class="now-playing-info">
                        <h3><?php echo htmlspecialchars($currentState['track']); ?></h3>
                        <p><?php echo htmlspecialchars($currentState['artist']); ?> &mdash; <?php echo htmlspecialchars($currentState['album']); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="nothing-playing">Nothing playing. Start some music<?php echo $isPlex ? ' in Plex' : ' on Spotify'; ?>!</p>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <!-- Platform Settings -->
            <div class="card">
                <h2><?php echo $isPlex ? 'Plex' : 'Spotify'; ?> Settings</h2>
                <form method="POST">
                    <?php echo csrfField(); ?>

                    <?php if ($isPlex): ?>
                    <div class="form-group">
                        <label for="plex_token">Plex Token</label>
                        <input type="password" id="plex_token" name="plex_token"
                               value="<?php echo htmlspecialchars($plexToken); ?>">
                    </div>
                    <div class="form-group">
                        <label for="plex_url">Plex Server URL</label>
                        <input type="text" id="plex_url" name="plex_url"
                               value="<?php echo htmlspecialchars($plexUrl); ?>">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="spotify_client_id">Spotify Client ID</label>
                        <input type="text" id="spotify_client_id" name="spotify_client_id"
                               value="<?php echo htmlspecialchars($spotifyClientId); ?>">
                    </div>
                    <div class="form-group">
                        <label for="spotify_client_secret">Spotify Client Secret</label>
                        <input type="password" id="spotify_client_secret" name="spotify_client_secret"
                               value="<?php echo htmlspecialchars($spotifyClientSecret); ?>">
                        <p class="hint">Get these from <a href="https://developer.spotify.com/dashboard" target="_blank" style="color: #60a5fa;">Spotify Developer Dashboard</a></p>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="site_url">Site URL</label>
                        <input type="url" id="site_url" name="site_url"
                               value="<?php echo htmlspecialchars(SITE_URL); ?>">
                        <p class="hint">No trailing slash. Used for OAuth callbacks.</p>
                    </div>

                    <hr style="border-color: rgba(255,255,255,0.1); margin: 24px 0;">
                    <h3 style="font-size: 14px; margin-bottom: 16px; color: #888;">AI Notes Provider</h3>

                    <div class="form-group">
                        <label for="ai_provider">AI Provider</label>
                        <select id="ai_provider" name="ai_provider" onchange="updateAIFields()">
                            <option value="gemini" <?php echo $aiProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini (Free)</option>
                            <option value="groq" <?php echo $aiProvider === 'groq' ? 'selected' : ''; ?>>Groq (Free)</option>
                            <option value="openai" <?php echo $aiProvider === 'openai' ? 'selected' : ''; ?>>OpenAI (Paid)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ai_model">Model</label>
                        <select id="ai_model" name="ai_model">
                            <!-- Options populated by JS -->
                        </select>
                    </div>

                    <p style="font-size: 12px; color: #666; margin: 16px 0 8px;">API Keys (all saved, only active provider is used)</p>

                    <div class="form-group">
                        <label for="openai_api_key">OpenAI API Key</label>
                        <input type="password" id="openai_api_key" name="openai_api_key"
                               value="<?php echo htmlspecialchars(defined('OPENAI_API_KEY') ? OPENAI_API_KEY : ''); ?>">
                        <p class="hint">Required for OpenAI notes AND TTS voice. <a href="https://platform.openai.com/api-keys" target="_blank" style="color: #60a5fa;">Get key</a></p>
                    </div>
                    <div class="form-group">
                        <label for="gemini_api_key">Gemini API Key</label>
                        <input type="password" id="gemini_api_key" name="gemini_api_key"
                               value="<?php echo htmlspecialchars(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''); ?>">
                        <p class="hint">Free tier available. <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #60a5fa;">Get free key</a></p>
                    </div>
                    <div class="form-group">
                        <label for="groq_api_key">Groq API Key</label>
                        <input type="password" id="groq_api_key" name="groq_api_key"
                               value="<?php echo htmlspecialchars(defined('GROQ_API_KEY') ? GROQ_API_KEY : ''); ?>">
                        <p class="hint">Free, very fast. <a href="https://console.groq.com/keys" target="_blank" style="color: #60a5fa;">Get free key</a></p>
                    </div>

                    <div class="form-group">
                        <label for="dj_voice">DJ Voice (TTS)</label>
                        <select id="dj_voice" name="dj_voice" onchange="updateTTSHint()">
                            <option value="off" <?php echo $djVoice === 'off' ? 'selected' : ''; ?>>Off</option>
                            <option value="free" <?php echo $djVoice === 'free' ? 'selected' : ''; ?>>Free (Web Speech)</option>
                            <option value="groq" <?php echo $djVoice === 'groq' ? 'selected' : ''; ?>>Groq TTS (Free, limited) - Short intros only</option>
                            <option value="openai" <?php echo $djVoice === 'openai' ? 'selected' : ''; ?>>OpenAI TTS (Paid) - Requires OpenAI key</option>
                        </select>
                        <p class="hint" id="tts_hint">Free voice won't pause music on iPhone.</p>
                        <p class="hint" id="groq_tts_hint" style="display: none; color: #fbbf24;">
                            Limited to ~180 chars, may mispronounce numbers. <a href="https://console.groq.com/playground?model=canopylabs%2Forpheus-v1-english" target="_blank" style="color: #60a5fa;">Accept terms first</a>
                        </p>
                    </div>
                    <script>
                    function updateTTSHint() {
                        const voice = document.getElementById('dj_voice').value;
                        const groqHint = document.getElementById('groq_tts_hint');
                        groqHint.style.display = voice === 'groq' ? 'block' : 'none';
                    }
                    updateTTSHint();
                    </script>

                    <script>
                    const aiModels = {
                        openai: [
                            {value: 'gpt-4.1-nano', label: 'GPT-4.1 Nano (Cheapest)'},
                            {value: 'gpt-4.1-mini', label: 'GPT-4.1 Mini (Recommended)'},
                            {value: 'gpt-4.1', label: 'GPT-4.1'},
                            {value: 'gpt-5-nano', label: 'GPT-5 Nano'},
                            {value: 'gpt-5-mini', label: 'GPT-5 Mini'},
                            {value: 'gpt-4o-mini', label: 'GPT-4o Mini'},
                            {value: 'gpt-4o', label: 'GPT-4o'}
                        ],
                        gemini: [
                            {value: 'gemini-2.5-flash-lite', label: 'Gemini 2.5 Flash Lite (Best free limits)'},
                            {value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash'},
                            {value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash'}
                        ],
                        groq: [
                            {value: 'llama-3.3-70b-versatile', label: 'Llama 3.3 70B (Best)'},
                            {value: 'llama-3.1-8b-instant', label: 'Llama 3.1 8B (Fastest)'},
                            {value: 'mixtral-8x7b-32768', label: 'Mixtral 8x7B'}
                        ]
                    };
                    const aiInstructions = {
                        openai: '<strong>OpenAI</strong> - Paid, most accurate<br><a href="https://platform.openai.com/api-keys" target="_blank" style="color: #60a5fa;">Get API key from OpenAI</a>',
                        gemini: '<strong>Google Gemini</strong> - Free tier available<br><a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #60a5fa;">Get free API key from Google AI Studio</a>',
                        groq: '<strong>Groq</strong> - Free, very fast<br><a href="https://console.groq.com/keys" target="_blank" style="color: #60a5fa;">Get free API key from Groq</a>'
                    };
                    const currentModel = '<?php echo htmlspecialchars($aiModel); ?>';

                    function updateAIFields() {
                        const provider = document.getElementById('ai_provider').value;
                        const modelSelect = document.getElementById('ai_model');

                        // Update models
                        modelSelect.innerHTML = '';
                        aiModels[provider].forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.value;
                            opt.textContent = m.label;
                            if (m.value === currentModel) opt.selected = true;
                            modelSelect.appendChild(opt);
                        });
                    }
                    updateAIFields();
                    </script>
                    <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2>Change Password</h2>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <p class="hint">Minimum 8 characters</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Help -->
        <div class="card">
            <h2>Setup Help</h2>
            <?php if ($isPlex): ?>
            <div class="info-row">
                <span class="info-label">Step 1</span>
                <span class="info-value">Copy the Webhook URL above</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 2</span>
                <span class="info-value">Go to Plex Settings > Webhooks > Add Webhook</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 3</span>
                <span class="info-value">Paste the URL and save</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 4</span>
                <span class="info-value">Open the Display URL in your browser or Tesla</span>
            </div>
            <div class="info-row">
                <span class="info-label">Plex Token Help</span>
                <span class="info-value"><a href="https://support.plex.tv/articles/204059436-finding-an-authentication-token-x-plex-token/" target="_blank">How to find your Plex Token</a></span>
            </div>
            <?php else: ?>
            <div class="info-row">
                <span class="info-label">Step 1</span>
                <span class="info-value">Create app at <a href="https://developer.spotify.com/dashboard" target="_blank">Spotify Developer Dashboard</a></span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 2</span>
                <span class="info-value">Add redirect URI: <?php echo SITE_URL; ?>/api/spotify-callback.php</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 3</span>
                <span class="info-value">Enter Client ID and Secret above, then save</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 4</span>
                <span class="info-value">Click "Connect Spotify" and authorize</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 5</span>
                <span class="info-value">Set up cron job to run api/spotify-poll.php every 5-10 seconds</span>
            </div>
            <div class="info-row">
                <span class="info-label">Step 6</span>
                <span class="info-value">Open the Display URL in your browser or Tesla</span>
            </div>
            <?php endif; ?>
        </div>

        <footer style="text-align: center; margin-top: 40px; padding: 20px; color: #666; font-size: 13px;">
            LiveDJ v1.0.0 · Made with ♥ in Wilmington, NC<br>
            <a href="https://ko-fi.com/U6U01R3X7M" target="_blank" style="color: #72a4f2; text-decoration: none;">Buy me a coffee</a>
        </footer>
    </div>
</body>
</html>
