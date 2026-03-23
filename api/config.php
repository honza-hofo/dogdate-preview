<?php
/**
 * DogDate API - Configuration & Helpers
 */

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session
session_start();

// CORS headers for local development
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database path
define('DB_PATH', __DIR__ . '/../data/dogdate.db');
define('UPLOAD_DIR', __DIR__ . '/../data/uploads/');
define('UPLOAD_URL', '/data/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Create data directory if not exists
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $pdo;
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, int $statusCode = 200): void {
    http_response_code($statusCode);
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
 * Simple rate limiting via SQLite (for login)
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 60): void {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Create rate limit table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        action TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Clean old entries
    $db->prepare("DELETE FROM rate_limits WHERE created_at < datetime('now', ? || ' seconds')")
       ->execute(["-$windowSeconds"]);

    // Count recent attempts
    $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > datetime('now', ? || ' seconds')");
    $stmt->execute([$ip, $action, "-$windowSeconds"]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        jsonError("Příliš mnoho pokusů. Zkuste to znovu za minutu.", 429);
    }

    // Log this attempt
    $stmt = $db->prepare("INSERT INTO rate_limits (ip, action) VALUES (?, ?)");
    $stmt->execute([$ip, $action]);
}

/**
 * Initialize database if not exists
 */
function ensureDatabase(): void {
    if (!file_exists(DB_PATH)) {
        require_once __DIR__ . '/init-db.php';
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
