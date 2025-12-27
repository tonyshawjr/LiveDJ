<?php
/**
 * LiveDJ - Entry Point
 * Shows display directly or install wizard
 */

// Check if config exists
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

// Show display directly
require __DIR__ . '/display.php';
