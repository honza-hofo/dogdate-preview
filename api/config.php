<?php
/**
 * DogDate API - Configuration & Helpers
 */

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session cookie security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Session
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CORS headers for local development
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Validate CSRF token from X-CSRF-Token header
 */
function validateCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonError('Neplatný CSRF token. Obnovte stránku a zkuste to znovu.', 403);
    }
}

// Enforce CSRF validation for state-changing requests (POST, DELETE)
if (isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    validateCsrf();
}

// Database path (SQLite fallback for local dev)
define('DB_PATH', __DIR__ . '/../data/dogdate.db');
define('UPLOAD_DIR', __DIR__ . '/../data/uploads/');
define('UPLOAD_URL', '/data/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Table prefix for MySQL mode (avoids WP table conflicts)
define('DOGDATE_TABLE_PREFIX', 'dogdate_');

// Detect database mode: 'mysql' or 'sqlite'
define('DOGDATE_DB_MODE', defined('ABSPATH') || !extension_loaded('pdo_sqlite') ? 'mysql' : 'sqlite');

// Create data directory if not exists (only needed for SQLite/uploads)
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Get table name with prefix (only in MySQL mode)
 */
function T(string $table): string {
    if (DOGDATE_DB_MODE === 'mysql') {
        return DOGDATE_TABLE_PREFIX . $table;
    }
    return $table;
}

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DOGDATE_DB_MODE === 'mysql') {
            // MySQL mode - use WordPress DB credentials if available, or direct config
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
                $host = DB_HOST;
                $name = DB_NAME;
                $user = DB_USER;
                $pass = DB_PASSWORD;
            } else {
                // Try loading wp-config.php to get credentials
                $wpConfigPaths = [
                    __DIR__ . '/../../wp-config.php',
                    __DIR__ . '/../../../wp-config.php',
                    __DIR__ . '/../../../../wp-config.php',
                ];
                $loaded = false;
                foreach ($wpConfigPaths as $path) {
                    if (file_exists($path)) {
                        // Extract DB constants without loading full WP
                        $wpConfig = file_get_contents($path);
                        if (preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $wpConfig, $m)) $host = $m[1];
                        if (preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $wpConfig, $m)) $name = $m[1];
                        if (preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/", $wpConfig, $m)) $user = $m[1];
                        if (preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $wpConfig, $m)) $pass = $m[1];
                        if (isset($host, $name, $user, $pass)) {
                            $loaded = true;
                            break;
                        }
                    }
                }
                if (!$loaded) {
                    throw new \RuntimeException('Cannot find MySQL credentials. Define DB_HOST/DB_NAME/DB_USER/DB_PASSWORD or place in WordPress context.');
                }
            }
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // SQLite mode - local development fallback
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
    }
    return $pdo;
}

/**
 * Send JSON response and exit (includes CSRF token for client use)
 */
function jsonResponse($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    if (is_array($data) && !empty($_SESSION['csrf_token'])) {
        $data['_csrf_token'] = $_SESSION['csrf_token'];
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function jsonError(string $message, int $statusCode = 400): void {
    jsonResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Require authenticated user, return user ID
 */
function requireAuth(): int {
    if (empty($_SESSION['user_id'])) {
        jsonError('Nejste přihlášen/a.', 401);
    }
    return (int)$_SESSION['user_id'];
}

/**
 * Get optional authenticated user ID (0 if not logged in)
 */
function getAuthUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Sanitize string input
 */
function sanitize(?string $input): string {
    if ($input === null) return '';
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

/**
 * Get request body as JSON
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Simple rate limiting (works with both MySQL and SQLite)
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 60): void {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $table = T('rate_limits');

    if (DOGDATE_DB_MODE === 'mysql') {
        // Create rate limit table if not exists (MySQL)
        $db->exec("CREATE TABLE IF NOT EXISTS `$table` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Clean old entries
        $db->prepare("DELETE FROM `$table` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
           ->execute([$windowSeconds]);

        // Count recent attempts
        $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE ip = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $action, $windowSeconds]);
    } else {
        // SQLite
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            action TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->prepare("DELETE FROM rate_limits WHERE created_at < datetime('now', ? || ' seconds')")
           ->execute(["-$windowSeconds"]);

        $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > datetime('now', ? || ' seconds')");
        $stmt->execute([$ip, $action, "-$windowSeconds"]);
    }

    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        jsonError("Příliš mnoho pokusů. Zkuste to znovu za minutu.", 429);
    }

    // Log this attempt
    $stmt = $db->prepare("INSERT INTO `$table` (ip, action) VALUES (?, ?)");
    $stmt->execute([$ip, $action]);
}

/**
 * Initialize database if not exists
 */
function ensureDatabase(): void {
    if (DOGDATE_DB_MODE === 'mysql') {
        // Always run init for MySQL (it checks IF NOT EXISTS internally)
        require_once __DIR__ . '/init-db.php';
    } else {
        // SQLite: check if file exists
        if (!file_exists(DB_PATH)) {
            require_once __DIR__ . '/init-db.php';
        }
    }
}

/**
 * Haversine distance between two GPS coordinates in km
 */
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// Auto-initialize database
ensureDatabase();
