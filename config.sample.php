<?php
/**
 * LiveDJ Configuration
 * Copy this file to config.php and fill in your details
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'livedj');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site URL (no trailing slash)
define('SITE_URL', 'https://yourdomain.com');

// Platform: 'plex' or 'spotify'
define('PLATFORM', 'spotify');

// AI Provider: 'openai', 'gemini', or 'groq'
define('AI_PROVIDER', 'gemini');
define('AI_MODEL', 'gemini-2.0-flash');

// API Keys for each provider (only the active provider's key is used)
define('OPENAI_API_KEY', '');
define('GEMINI_API_KEY', '');
define('GROQ_API_KEY', '');
