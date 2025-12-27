<?php
/**
 * LiveDJ - Installation Wizard
 * Supports Plex and Spotify
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/db.php';
    try {
        if (getSetting('installed') === '1') {
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        // Continue with install
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Database setup
    if (isset($_POST['setup_database'])) {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');

        // Test connection
        try {
            $testPdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Create config file
            $config = "<?php\n";
            $config .= "// LiveDJ Configuration - Generated " . date('Y-m-d H:i:s') . "\n\n";
            $config .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $config .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $config .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $config .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $config .= "define('SITE_URL', " . var_export($siteUrl, true) . ");\n";

            file_put_contents(__DIR__ . '/config.php', $config);

            // Include and create tables
            require_once __DIR__ . '/config.php';
            require_once __DIR__ . '/includes/db.php';
            createTables();

            header('Location: install.php?step=2');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }

    // Step 2: Choose platform
    if (isset($_POST['choose_platform'])) {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/includes/db.php';

        $platform = $_POST['platform'] ?? 'spotify';
        setSetting('platform', $platform);

        // Add PLATFORM to config.php
        $configContent = file_get_contents(__DIR__ . '/config.php');
        if (strpos($configContent, 'PLATFORM') === false) {
            $configContent .= "\ndefine('PLATFORM', " . var_export($platform, true) . ");\n";
            file_put_contents(__DIR__ . '/config.php', $configContent);
        }

        header('Location: install.php?step=3');
        exit;
    }

    // Step 3: Platform settings
    if (isset($_POST['save_platform_settings'])) {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/includes/db.php';

        $platform = getSetting('platform', 'spotify');

        if ($platform === 'spotify') {
            setSetting('spotify_client_id', trim($_POST['spotify_client_id'] ?? ''));
            setSetting('spotify_client_secret', trim($_POST['spotify_client_secret'] ?? ''));
        } else {
            setSetting('plex_token', trim($_POST['plex_token'] ?? ''));
            setSetting('plex_url', trim($_POST['plex_url'] ?? 'http://127.0.0.1:32400'));
            setSetting('webhook_token', bin2hex(random_bytes(16)));
        }

        // AI settings - write to config.php
        $aiProvider = trim($_POST['ai_provider'] ?? 'gemini');
        $aiModel = trim($_POST['ai_model'] ?? '');
        $aiApiKey = trim($_POST['ai_api_key'] ?? '');

        $configContent = file_get_contents(__DIR__ . '/config.php');
        if (strpos($configContent, 'AI_PROVIDER') === false) {
            $configContent .= "\n// AI Settings\n";
            $configContent .= "define('AI_PROVIDER', " . var_export($aiProvider, true) . ");\n";
            $configContent .= "define('AI_MODEL', " . var_export($aiModel, true) . ");\n";
            $configContent .= "define('OPENAI_API_KEY', '');\n";
            $configContent .= "define('GEMINI_API_KEY', " . var_export($aiApiKey, true) . ");\n";
            $configContent .= "define('GROQ_API_KEY', '');\n";
            file_put_contents(__DIR__ . '/config.php', $configContent);
        }

        setSetting('dj_voice', 'free');

        header('Location: install.php?step=4');
        exit;
    }

    // Step 4: Admin account
    if (isset($_POST['create_admin'])) {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/includes/db.php';

        $username = trim($_POST['admin_username'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            setSetting('admin_username', $username);
            setSetting('admin_password', password_hash($password, PASSWORD_DEFAULT));
            setSetting('installed', '1');

            header('Location: install.php?step=5');
            exit;
        }
    }
}

// Load settings if available
$platform = '';
if (file_exists(__DIR__ . '/config.php') && $step > 1) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/db.php';
    $platform = getSetting('platform', 'spotify');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiveDJ - Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0d0d0d 0%, #1a1a2e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            width: 100%;
            max-width: 500px;
        }
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #1DB954 0%, #1ed760 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .logo p {
            color: #888;
            margin-top: 8px;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 32px;
            backdrop-filter: blur(10px);
        }
        .steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #333;
        }
        .step-dot.active {
            background: #1DB954;
        }
        .step-dot.completed {
            background: #1DB954;
        }
        h2 {
            font-size: 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #ccc;
        }
        input, select {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #1DB954;
        }
        input::placeholder {
            color: #666;
        }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
        }
        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #1DB954;
            color: #000;
        }
        .btn-primary:hover {
            background: #1ed760;
            transform: translateY(-1px);
        }
        .platform-choice {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .platform-option {
            flex: 1;
            padding: 24px 16px;
            background: rgba(0,0,0,0.3);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .platform-option:hover {
            border-color: rgba(255,255,255,0.3);
        }
        .platform-option.selected {
            border-color: #1DB954;
            background: rgba(29, 185, 84, 0.1);
        }
        .platform-option input {
            display: none;
        }
        .platform-option .icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .platform-option .name {
            font-weight: 600;
            font-size: 16px;
        }
        .platform-option .desc {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        .instructions {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .instructions h3 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #1DB954;
        }
        .instructions ol {
            margin-left: 20px;
            line-height: 1.8;
            color: #aaa;
        }
        .instructions a {
            color: #1DB954;
        }
        .instructions code {
            background: rgba(255,255,255,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        .success-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 24px;
        }
        .urls {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .url-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 13px;
        }
        .url-row:last-child {
            border-bottom: none;
        }
        .url-label {
            color: #888;
        }
        .url-value {
            color: #1DB954;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>LiveDJ</h1>
            <p>Your personal music DJ</p>
        </div>

        <div class="card">
            <div class="steps">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="step-dot <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>"></div>
                <?php endfor; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <!-- Step 1: Database -->
                <h2>Database Setup</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" placeholder="livedj" required>
                    </div>
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                    <div class="form-group">
                        <label for="site_url">Site URL</label>
                        <input type="url" id="site_url" name="site_url" placeholder="https://yourdomain.com" required>
                        <p class="hint">No trailing slash. Used for callbacks and links.</p>
                    </div>
                    <button type="submit" name="setup_database" class="btn btn-primary">Continue</button>
                </form>

            <?php elseif ($step == 2): ?>
                <!-- Step 2: Choose Platform -->
                <h2>Choose Your Platform</h2>
                <form method="POST">
                    <div class="platform-choice">
                        <label class="platform-option selected" onclick="selectPlatform('spotify')">
                            <input type="radio" name="platform" value="spotify" checked>
                            <div class="icon">🎵</div>
                            <div class="name">Spotify</div>
                            <div class="desc">Stream from Spotify</div>
                        </label>
                        <label class="platform-option" onclick="selectPlatform('plex')">
                            <input type="radio" name="platform" value="plex">
                            <div class="icon">🎬</div>
                            <div class="name">Plex</div>
                            <div class="desc">Use your Plex server</div>
                        </label>
                    </div>
                    <button type="submit" name="choose_platform" class="btn btn-primary">Continue</button>
                </form>
                <script>
                    function selectPlatform(platform) {
                        document.querySelectorAll('.platform-option').forEach(el => el.classList.remove('selected'));
                        document.querySelector('input[value="' + platform + '"]').checked = true;
                        document.querySelector('input[value="' + platform + '"]').closest('.platform-option').classList.add('selected');
                    }
                </script>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Platform Settings -->
                <h2><?php echo $platform === 'spotify' ? 'Spotify' : 'Plex'; ?> Setup</h2>
                <form method="POST">
                    <?php if ($platform === 'spotify'): ?>
                        <div class="instructions">
                            <h3>How to get Spotify API credentials:</h3>
                            <ol>
                                <li>Go to <a href="https://developer.spotify.com/dashboard" target="_blank">Spotify Developer Dashboard</a></li>
                                <li>Log in and click <strong>Create App</strong></li>
                                <li>Fill in App name: <code>LiveDJ</code></li>
                                <li>Add Redirect URI: <code><?php echo SITE_URL; ?>/api/spotify-callback.php</code></li>
                                <li>Check <strong>Web API</strong> and save</li>
                                <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong></li>
                            </ol>
                        </div>
                        <div class="form-group">
                            <label for="spotify_client_id">Spotify Client ID</label>
                            <input type="text" id="spotify_client_id" name="spotify_client_id" required>
                        </div>
                        <div class="form-group">
                            <label for="spotify_client_secret">Spotify Client Secret</label>
                            <input type="password" id="spotify_client_secret" name="spotify_client_secret" required>
                        </div>
                    <?php else: ?>
                        <div class="instructions">
                            <h3>How to get your Plex Token:</h3>
                            <ol>
                                <li>Go to <a href="https://app.plex.tv" target="_blank">app.plex.tv</a> and sign in</li>
                                <li>Play any media, then click the <strong>...</strong> menu</li>
                                <li>Select <strong>Get Info</strong> > <strong>View XML</strong></li>
                                <li>Find <code>X-Plex-Token=XXXXX</code> in the URL</li>
                            </ol>
                        </div>
                        <div class="form-group">
                            <label for="plex_token">Plex Token</label>
                            <input type="password" id="plex_token" name="plex_token" required>
                        </div>
                        <div class="form-group">
                            <label for="plex_url">Plex Server URL</label>
                            <input type="text" id="plex_url" name="plex_url" value="http://127.0.0.1:32400">
                            <p class="hint">Use your public URL if accessing remotely</p>
                        </div>
                    <?php endif; ?>

                    <hr style="border-color: rgba(255,255,255,0.1); margin: 24px 0;">
                    <h3 style="font-size: 14px; margin-bottom: 16px; color: #1DB954;">AI Notes (Optional)</h3>
                    <p class="hint" style="margin-bottom: 16px;">Generate interesting facts about artists and albums</p>

                    <div class="form-group">
                        <label for="ai_provider">AI Provider</label>
                        <select id="ai_provider" name="ai_provider" onchange="updateAISetup()">
                            <option value="gemini">Google Gemini (Free)</option>
                            <option value="groq">Groq (Free)</option>
                            <option value="openai">OpenAI (Paid)</option>
                        </select>
                    </div>
                    <div class="instructions" id="ai_setup_instructions">
                        <!-- Populated by JS -->
                    </div>
                    <div class="form-group">
                        <label for="ai_api_key">API Key</label>
                        <input type="password" id="ai_api_key" name="ai_api_key">
                    </div>
                    <div class="form-group">
                        <label for="ai_model">Model</label>
                        <select id="ai_model" name="ai_model">
                            <!-- Populated by JS -->
                        </select>
                    </div>

                    <script>
                    const setupInstructions = {
                        gemini: '<h3>Get your free Gemini API key:</h3><ol><li>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li><li>Sign in with Google</li><li>Click "Create API Key"</li><li>Copy and paste below</li></ol>',
                        groq: '<h3>Get your free Groq API key:</h3><ol><li>Go to <a href="https://console.groq.com/keys" target="_blank">Groq Console</a></li><li>Create an account (free)</li><li>Click "Create API Key"</li><li>Copy and paste below</li></ol>',
                        openai: '<h3>Get your OpenAI API key:</h3><ol><li>Go to <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></li><li>Create an account and add payment</li><li>Create new API key</li><li>Copy and paste below</li></ol>'
                    };
                    const setupModels = {
                        gemini: [{v: 'gemini-2.5-flash-lite', l: 'Gemini 2.5 Flash Lite (Best free limits)'}, {v: 'gemini-2.5-flash', l: 'Gemini 2.5 Flash'}],
                        groq: [{v: 'llama-3.3-70b-versatile', l: 'Llama 3.3 70B (Recommended)'}, {v: 'llama-3.1-8b-instant', l: 'Llama 3.1 8B (Fast)'}],
                        openai: [{v: 'gpt-4o-mini', l: 'GPT-4o Mini (Recommended)'}, {v: 'gpt-4o', l: 'GPT-4o'}]
                    };
                    function updateAISetup() {
                        const p = document.getElementById('ai_provider').value;
                        document.getElementById('ai_setup_instructions').innerHTML = setupInstructions[p];
                        const sel = document.getElementById('ai_model');
                        sel.innerHTML = '';
                        setupModels[p].forEach(m => {
                            const o = document.createElement('option');
                            o.value = m.v; o.textContent = m.l;
                            sel.appendChild(o);
                        });
                    }
                    updateAISetup();
                    </script>

                    <button type="submit" name="save_platform_settings" class="btn btn-primary">Continue</button>
                </form>

            <?php elseif ($step == 4): ?>
                <!-- Step 4: Admin Account -->
                <h2>Create Admin Account</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_username">Username</label>
                        <input type="text" id="admin_username" name="admin_username" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="8">
                        <p class="hint">Minimum 8 characters</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="create_admin" class="btn btn-primary">Complete Setup</button>
                </form>

            <?php elseif ($step == 5): ?>
                <!-- Step 5: Complete -->
                <div class="success-icon">🎉</div>
                <h2>Installation Complete!</h2>

                <?php if ($platform === 'spotify'): ?>
                    <p style="text-align: center; color: #888; margin-bottom: 20px;">
                        One more step: Connect your Spotify account
                    </p>
                    <div class="urls">
                        <div class="url-row">
                            <span class="url-label">Display URL</span>
                            <span class="url-value"><?php echo SITE_URL; ?></span>
                        </div>
                        <div class="url-row">
                            <span class="url-label">Admin Panel</span>
                            <span class="url-value"><?php echo SITE_URL; ?>/admin</span>
                        </div>
                    </div>
                    <a href="<?php require_once __DIR__ . '/includes/spotify.php'; echo getSpotifyAuthUrl(); ?>" class="btn btn-primary" style="display: block; text-align: center; text-decoration: none; margin-bottom: 12px;">
                        Connect Spotify
                    </a>
                <?php else: ?>
                    <?php $webhookUrl = SITE_URL . '/api/webhook.php?token=' . getSetting('webhook_token'); ?>
                    <div class="urls">
                        <div class="url-row">
                            <span class="url-label">Webhook URL</span>
                            <span class="url-value"><?php echo $webhookUrl; ?></span>
                        </div>
                        <div class="url-row">
                            <span class="url-label">Display URL</span>
                            <span class="url-value"><?php echo SITE_URL; ?></span>
                        </div>
                        <div class="url-row">
                            <span class="url-label">Admin Panel</span>
                            <span class="url-value"><?php echo SITE_URL; ?>/admin</span>
                        </div>
                    </div>
                    <p style="text-align: center; color: #888; margin-bottom: 20px;">
                        Add the webhook URL to Plex Settings > Webhooks
                    </p>
                <?php endif; ?>

                <a href="admin/" class="btn btn-primary" style="display: block; text-align: center; text-decoration: none;">
                    Go to Admin Panel
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
