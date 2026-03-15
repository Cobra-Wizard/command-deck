<?php
/**
 * Command Deck — API
 *
 * All AJAX requests from the dashboard come here.
 * Routes are determined by the ?action= query parameter.
 */
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Auth check — every request must have a valid session
if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------
    // List all resources
    // -------------------------------------------------------
    case 'list':
        respond(loadResources());
        break;

    // -------------------------------------------------------
    // Add a new resource
    // -------------------------------------------------------
    case 'add':
        $data = getPostData();
        $resources = loadResources();
        $maxId = 0;
        foreach ($resources as $r) {
            if ($r['id'] > $maxId) $maxId = $r['id'];
        }
        $data['id'] = $maxId + 1;
        $resources[] = $data;
        saveResources($resources);
        respond($data);
        break;

    // -------------------------------------------------------
    // Update an existing resource
    // -------------------------------------------------------
    case 'update':
        $id   = (int)($_GET['id'] ?? 0);
        $data = getPostData();
        $resources = loadResources();
        $found = false;
        foreach ($resources as &$r) {
            if ($r['id'] === $id) {
                $data['id'] = $id;
                $r = $data;
                $found = true;
                break;
            }
        }
        unset($r);
        if (!$found) {
            http_response_code(404);
            die(json_encode(['status' => 'error', 'message' => 'Resource not found']));
        }
        saveResources($resources);
        respond($data);
        break;

    // -------------------------------------------------------
    // Delete a resource
    // -------------------------------------------------------
    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        $resources = loadResources();
        $resources = array_values(array_filter($resources, fn($r) => $r['id'] !== $id));
        saveResources($resources);
        respond(null);
        break;

    // -------------------------------------------------------
    // Check online/offline status of all resources
    // Uses server-side curl so there are no CORS issues
    // -------------------------------------------------------
    case 'check_all':
        $resources = loadResources();
        $results = [];
        foreach ($resources as $r) {
            $results[$r['id']] = checkUrl($r['url'] ?? '');
        }
        respond($results);
        break;

    // -------------------------------------------------------
    // Scan a subnet for active devices
    // Uses curl_multi for fast parallel port scanning
    // -------------------------------------------------------
    case 'scan':
        set_time_limit(120);
        $subnet = preg_replace('/[^0-9.]/', '', $_GET['subnet'] ?? DEFAULT_SUBNET);
        $results = scanSubnet($subnet, SCAN_PORTS, SCAN_TIMEOUT_MS, SCAN_BATCH_SIZE);

        // Mark IPs that are already in our resource list
        $resources = loadResources();
        $knownIps = [];
        foreach ($resources as $r) {
            $parsed = parse_url($r['url'] ?? '');
            if (isset($parsed['host'])) {
                $knownIps[] = $parsed['host'];
            }
        }
        foreach ($results as &$entry) {
            $entry['known'] = in_array($entry['ip'], $knownIps);
        }
        unset($entry);

        respond($results);
        break;

    default:
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Unknown action']));
}


// =============================================================
//  Helper Functions
// =============================================================

/** Send a success response */
function respond($data): void {
    echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_SLASHES);
    exit;
}

/** Load resources from JSON file */
function loadResources(): array {
    if (!file_exists(DATA_FILE)) return [];
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true) ?: [];
}

/** Save resources to JSON file */
function saveResources(array $resources): void {
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(
        DATA_FILE,
        json_encode($resources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

/** Parse and sanitise the POST body */
function getPostData(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Invalid JSON body']));
    }

    // Sanitise URL — only allow safe protocols
    $url = trim($data['url'] ?? '');
    if ($url !== '' && !preg_match('~^(https?|smb|ftp)://~i', $url)) {
        if (!str_contains($url, '://')) {
            $url = 'http://' . $url;
        } else {
            $url = '';
        }
    }

    // Normalise tags: accept array or comma-separated string
    $rawTags = $data['tags'] ?? [];
    if (is_string($rawTags)) {
        $rawTags = explode(',', $rawTags);
    }
    $tags = array_values(array_filter(array_map('trim', $rawTags), 'strlen'));

    return [
        'id'          => 0,
        'name'        => strip_tags(trim($data['name'] ?? '')),
        'url'         => filter_var($url, FILTER_SANITIZE_URL),
        'category'    => strip_tags(trim($data['category'] ?? 'Other')),
        'tags'        => $tags,
        'description' => strip_tags(trim($data['description'] ?? '')),
        'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'] ?? '')
                            ? $data['color']
                            : '#3b82f6',
    ];
}

/**
 * Check whether a URL is reachable.
 * Returns 'online', 'offline', or 'unknown'.
 */
function checkUrl(string $url): string {
    if ($url === '' || str_starts_with($url, 'file:') || str_starts_with($url, 'smb:')) {
        return 'unknown';
    }

    // Prefer curl (supports HTTPS, redirects, custom timeout)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,       // HEAD request
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'CommandDeck/2.0',
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($err === 0 && $code > 0) return 'online';
        return 'offline';
    }

    // Fallback: raw TCP check with fsockopen
    $parts = parse_url($url);
    $host  = $parts['host'] ?? '';
    $port  = $parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80);
    $conn  = @fsockopen($host, $port, $errno, $errstr, 3);
    if ($conn) { fclose($conn); return 'online'; }
    return 'offline';
}

/**
 * Scan a /24 subnet for devices with open ports.
 *
 * Uses curl_multi for fast parallel scanning. Falls back to
 * sequential fsockopen if curl is unavailable.
 *
 * Returns an array of [ ip, ports[], services[] ].
 */
function scanSubnet(string $subnet, array $ports, int $timeoutMs, int $batchSize): array {
    $found = [];

    if (function_exists('curl_multi_init')) {
        // --- Fast path: parallel curl_multi ---
        for ($start = 1; $start <= 254; $start += $batchSize) {
            $end     = min($start + $batchSize - 1, 254);
            $multi   = curl_multi_init();
            $handles = [];

            for ($i = $start; $i <= $end; $i++) {
                $ip = "$subnet.$i";
                foreach ($ports as $port) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL              => "http://$ip:$port",
                        CURLOPT_CONNECT_ONLY     => true,
                        CURLOPT_TIMEOUT_MS       => $timeoutMs,
                        CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
                    ]);
                    $key = "$ip:$port";
                    $handles[$key] = $ch;
                    curl_multi_add_handle($multi, $ch);
                }
            }

            // Execute batch
            do {
                $status = curl_multi_exec($multi, $active);
                if ($active) curl_multi_select($multi, 0.01);
            } while ($active && $status === CURLM_OK);

            // Drain completed-handle results (curl_errno is unreliable in multi mode)
            $succeeded = [];
            while ($info = curl_multi_info_read($multi)) {
                if ($info['msg'] === CURLMSG_DONE && $info['result'] === CURLE_OK) {
                    $succeeded[spl_object_id($info['handle'])] = true;
                }
            }

            // Collect results — only record truly-open ports
            foreach ($handles as $key => $ch) {
                if (isset($succeeded[spl_object_id($ch)])) {
                    [$ip, $port] = explode(':', $key);
                    $p = (int)$port;
                    if (!isset($found[$ip])) {
                        $found[$ip] = ['ip' => $ip, 'ports' => [], 'services' => []];
                    }
                    $found[$ip]['ports'][]    = $p;
                    $found[$ip]['services'][] = PORT_NAMES[$p] ?? "Port $p";
                }
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_close($multi);
        }
    } else {
        // --- Slow path: sequential fsockopen (fewer ports) ---
        $quickPorts = [80, 443, 22, 8006, 8080, 8123];
        $timeout    = max($timeoutMs / 1000, 0.05);

        for ($i = 1; $i <= 254; $i++) {
            $ip = "$subnet.$i";
            foreach ($quickPorts as $port) {
                $conn = @fsockopen($ip, $port, $errno, $errstr, $timeout);
                if ($conn) {
                    fclose($conn);
                    if (!isset($found[$ip])) {
                        $found[$ip] = ['ip' => $ip, 'ports' => [], 'services' => []];
                    }
                    $found[$ip]['ports'][]    = $port;
                    $found[$ip]['services'][] = PORT_NAMES[$port] ?? "Port $port";
                }
            }
        }
    }

    ksort($found);
    return array_values($found);
}
