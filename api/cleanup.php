<?php
/**
 * DogDate - Automated Data Cleanup (GDPR Compliance)
 *
 * Run daily via cron: 0 3 * * * php /path/to/cleanup.php
 * Or manually: php cleanup.php
 *
 * Actions:
 * - Delete GPS coordinates older than 48 hours
 * - Delete messages older than 12 months
 * - Delete rate_limits older than 24 hours
 * - Delete accounts marked for deletion (deleted_at NOT NULL)
 * - Log inactive accounts (no login for 24 months) - warning only
 */

// Allow CLI execution only (or authenticated admin)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit(1);
}

require_once __DIR__ . '/config.php';

$db = getDB();
$logEntries = [];
$totalDeleted = 0;

function logCleanup(string $action, int $count, string $details = ''): void {
    global $logEntries, $totalDeleted;
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'count' => $count,
        'details' => $details,
    ];
    $logEntries[] = $entry;
    $totalDeleted += $count;
    echo "[" . $entry['timestamp'] . "] $action: $count records" . ($details ? " ($details)" : "") . "\n";
}

echo "=== DogDate Cleanup - " . date('Y-m-d H:i:s') . " ===\n\n";

// --- Ensure cleanup_log table exists ---
if (DOGDATE_DB_MODE === 'mysql') {
    $db->exec("CREATE TABLE IF NOT EXISTS `" . T('cleanup_log') . "` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(255) NOT NULL,
        records_affected INT DEFAULT 0,
        details TEXT,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    $db->exec("CREATE TABLE IF NOT EXISTS cleanup_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        records_affected INTEGER DEFAULT 0,
        details TEXT,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// --- Ensure deleted_at column exists on users table ---
$usersTable = T('users');
try {
    if (DOGDATE_DB_MODE === 'mysql') {
        $cols = $db->query("SHOW COLUMNS FROM `$usersTable` LIKE 'deleted_at'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `$usersTable` ADD COLUMN deleted_at DATETIME DEFAULT NULL");
            $db->exec("ALTER TABLE `$usersTable` ADD COLUMN last_login_at DATETIME DEFAULT NULL");
            echo "Added deleted_at and last_login_at columns to users table.\n\n";
        }
    } else {
        $cols = $db->query("PRAGMA table_info($usersTable)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('deleted_at', $colNames)) {
            $db->exec("ALTER TABLE $usersTable ADD COLUMN deleted_at DATETIME DEFAULT NULL");
            echo "Added deleted_at column to users table.\n";
        }
        if (!in_array('last_login_at', $colNames)) {
            $db->exec("ALTER TABLE $usersTable ADD COLUMN last_login_at DATETIME DEFAULT NULL");
            echo "Added last_login_at column to users table.\n";
        }
    }
} catch (Exception $e) {
    echo "Note: Column check - " . $e->getMessage() . "\n";
}

// =========================================================================
// 1. DELETE GPS COORDINATES OLDER THAN 48 HOURS
// =========================================================================
echo "--- GPS Coordinates (>48h) ---\n";

if (DOGDATE_DB_MODE === 'mysql') {
    $stmt = $db->prepare("
        UPDATE `$usersTable`
        SET latitude = NULL, longitude = NULL
        WHERE last_location_update IS NOT NULL
        AND last_location_update < DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND latitude IS NOT NULL
    ");
} else {
    $stmt = $db->prepare("
        UPDATE $usersTable
        SET latitude = NULL, longitude = NULL
        WHERE last_location_update IS NOT NULL
        AND last_location_update < datetime('now', '-48 hours')
        AND latitude IS NOT NULL
    ");
}
$stmt->execute();
$gpsCleared = $stmt->rowCount();
logCleanup('GPS coordinates cleared', $gpsCleared, 'Older than 48 hours');

// =========================================================================
// 2. DELETE MESSAGES OLDER THAN 12 MONTHS
// =========================================================================
echo "\n--- Messages (>12 months) ---\n";

$messagesTable = T('messages');
if (DOGDATE_DB_MODE === 'mysql') {
    $stmt = $db->prepare("DELETE FROM `$messagesTable` WHERE created_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)");
} else {
    $stmt = $db->prepare("DELETE FROM $messagesTable WHERE created_at < datetime('now', '-12 months')");
}
$stmt->execute();
$messagesDeleted = $stmt->rowCount();
logCleanup('Old messages deleted', $messagesDeleted, 'Older than 12 months');

// =========================================================================
// 3. DELETE RATE LIMITS OLDER THAN 24 HOURS
// =========================================================================
echo "\n--- Rate Limits (>24h) ---\n";

$rateLimitsTable = T('rate_limits');
if (DOGDATE_DB_MODE === 'mysql') {
    $stmt = $db->prepare("DELETE FROM `$rateLimitsTable` WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
} else {
    $stmt = $db->prepare("DELETE FROM $rateLimitsTable WHERE created_at < datetime('now', '-24 hours')");
}
$stmt->execute();
$rateLimitsDeleted = $stmt->rowCount();
logCleanup('Rate limits purged', $rateLimitsDeleted, 'Older than 24 hours');

// =========================================================================
// 4. DELETE ACCOUNTS MARKED FOR DELETION (deleted_at IS NOT NULL)
// =========================================================================
echo "\n--- Accounts marked for deletion ---\n";

if (DOGDATE_DB_MODE === 'mysql') {
    $stmt = $db->query("SELECT id, name, email FROM `$usersTable` WHERE deleted_at IS NOT NULL");
} else {
    $stmt = $db->query("SELECT id, name, email FROM $usersTable WHERE deleted_at IS NOT NULL");
}
$markedUsers = $stmt->fetchAll();

$accountsDeleted = 0;
foreach ($markedUsers as $user) {
    // Delete user photos from filesystem
    $dogsTable = T('dogs');
    $photoStmt = $db->prepare("SELECT photo FROM `$dogsTable` WHERE user_id = ? AND photo IS NOT NULL");
    $photoStmt->execute([$user['id']]);
    $dogPhotos = $photoStmt->fetchAll();

    $avatarStmt = $db->prepare("SELECT avatar FROM `$usersTable` WHERE id = ?");
    $avatarStmt->execute([$user['id']]);
    $avatarRow = $avatarStmt->fetch();

    // Remove photo files
    $photosToDelete = [];
    if ($avatarRow && $avatarRow['avatar']) {
        $photosToDelete[] = $avatarRow['avatar'];
    }
    foreach ($dogPhotos as $dp) {
        if ($dp['photo']) $photosToDelete[] = $dp['photo'];
    }
    foreach ($photosToDelete as $photoPath) {
        $fullPath = __DIR__ . '/../' . ltrim($photoPath, '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    // Delete user (CASCADE will handle dogs, availability, matches, messages, consents)
    $delStmt = $db->prepare("DELETE FROM `$usersTable` WHERE id = ?");
    $delStmt->execute([$user['id']]);
    $accountsDeleted++;
    echo "  Deleted account: {$user['name']} ({$user['email']})\n";
}
logCleanup('Marked accounts deleted', $accountsDeleted, 'Users with deleted_at set');

// =========================================================================
// 5. LOG INACTIVE ACCOUNTS (no login for 24 months) - WARNING ONLY
// =========================================================================
echo "\n--- Inactive accounts (>24 months, warning only) ---\n";

if (DOGDATE_DB_MODE === 'mysql') {
    $stmt = $db->query("
        SELECT id, name, email, last_login_at, created_at
        FROM `$usersTable`
        WHERE deleted_at IS NULL
        AND (
            (last_login_at IS NOT NULL AND last_login_at < DATE_SUB(NOW(), INTERVAL 24 MONTH))
            OR (last_login_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 MONTH))
        )
    ");
} else {
    $stmt = $db->query("
        SELECT id, name, email, last_login_at, created_at
        FROM $usersTable
        WHERE deleted_at IS NULL
        AND (
            (last_login_at IS NOT NULL AND last_login_at < datetime('now', '-24 months'))
            OR (last_login_at IS NULL AND created_at < datetime('now', '-24 months'))
        )
    ");
}
$inactiveUsers = $stmt->fetchAll();

if (count($inactiveUsers) > 0) {
    echo "  WARNING: " . count($inactiveUsers) . " inactive accounts found (not auto-deleted):\n";
    foreach ($inactiveUsers as $u) {
        $lastActivity = $u['last_login_at'] ?? $u['created_at'];
        echo "    - ID:{$u['id']} {$u['name']} ({$u['email']}) - last activity: $lastActivity\n";
    }
} else {
    echo "  No inactive accounts found.\n";
}
logCleanup('Inactive accounts detected', count($inactiveUsers), 'No login for 24+ months (not deleted, warning only)');

// =========================================================================
// LOG ALL ACTIONS TO cleanup_log TABLE
// =========================================================================
echo "\n--- Saving cleanup log ---\n";

$logTable = T('cleanup_log');
$logStmt = $db->prepare("INSERT INTO `$logTable` (action, records_affected, details) VALUES (?, ?, ?)");
foreach ($logEntries as $entry) {
    $logStmt->execute([$entry['action'], $entry['count'], $entry['details']]);
}

echo "\n=== Cleanup complete. Total records affected: $totalDeleted ===\n";
