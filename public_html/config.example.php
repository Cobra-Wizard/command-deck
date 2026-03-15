<?php
/**
 * Command Deck — Configuration
 *
 * Copy this file to config.php and edit the values below.
 * config.php is .gitignored so your credentials stay local.
 */

// --- Branding ---
define('SITE_TITLE', 'Command Deck');
define('SITE_SUBTITLE', 'Your homelab control panel');

// --- Authentication ---
define('AUTH_PASSWORD', 'changeme');

// Session timeout in seconds (default: 8 hours)
define('SESSION_TIMEOUT', 28800);

// --- Data Storage ---
// Stored in .data/ inside public_html — nginx blocks dotfile directories
define('DATA_FILE', __DIR__ . '/.data/resources.json');

// --- Network Scanner ---
define('DEFAULT_SUBNET', '192.168.1');
define('SCAN_TIMEOUT_MS', 200);
define('SCAN_BATCH_SIZE', 50);
define('SCAN_PORTS', [
    22, 80, 443, 3000, 5000, 8000, 8006, 8008,
    8080, 8083, 8123, 8188, 8443, 9090, 11434, 32400
]);

// Human-readable port labels shown in scanner results
define('PORT_NAMES', [
    22    => 'SSH',
    80    => 'HTTP',
    443   => 'HTTPS',
    445   => 'SMB',
    3000  => 'Grafana',
    5000  => 'Frigate',
    8000  => 'HTTP-Alt',
    8006  => 'Proxmox',
    8008  => 'Unraid',
    8080  => 'HTTP-Proxy',
    8083  => 'HestiaCP',
    8123  => 'Home Assistant',
    8188  => 'ComfyUI',
    8443  => 'HTTPS-Alt',
    9090  => 'Cockpit',
    11434 => 'Ollama',
    32400 => 'Plex',
]);

// Categories available in the Add/Edit form
define('CATEGORIES', [
    'Infrastructure', 'Media', 'AI', 'Web', 'Home', 'Storage', 'Other'
]);
