<?php
/**
 * DogDate API - GDPR endpoints
 * Right to be forgotten, data portability, consent management
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'delete-account':
        handleDeleteAccount();
        break;
    case 'export-data':
        handleExportData();
        break;
    case 'consents':
        handleGetConsents();
        break;
    case 'withdraw-consent':
        handleWithdrawConsent();
        break;
    default:
        jsonError('Neznámá akce.', 400);
}

/**
 * GDPR Right to be forgotten - delete ALL user data
 */
function handleDeleteAccount(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda není povolena.', 405);
    }

    $userId = requireAuth();
    $db = getDB();

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    // Require password confirmation
    $password = $data['password'] ?? '';
    if (empty($password)) jsonError('Pro smazání účtu zadejte heslo.');

    $usersTable = T('users');
    $stmt = $db->prepare("SELECT password FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($password, $hash)) {
        jsonError('Nesprávné heslo.', 401);
    }

    $dogsTable = T('dogs');
    $availTable = T('availability');
    $matchesTable = T('matches');
    $messagesTable = T('messages');
    $consentsTable = T('gdpr_consents');
    $rateLimitsTable = T('rate_limits');

    // Delete uploaded files (avatar + dog photos)
    $stmt = $db->prepare("SELECT avatar FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $avatar = $stmt->fetchColumn();
    if ($avatar) {
        $path = __DIR__ . '/..' . $avatar;
        if (file_exists($path)) @unlink($path);
    }

    $stmt = $db->prepare("SELECT photo FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($photo = $stmt->fetchColumn()) {
        if ($photo) {
            $path = __DIR__ . '/..' . $photo;
            if (file_exists($path)) @unlink($path);
        }
    }

    // Delete ALL user data from all tables
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM `$messagesTable` WHERE sender_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM `$matchesTable` WHERE user1_id = ? OR user2_id = ?")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM `$availTable` WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM `$dogsTable` WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM `$consentsTable` WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM `$usersTable` WHERE id = ?")->execute([$userId]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Chyba při mazání dat: ' . $e->getMessage(), 500);
    }

    session_destroy();

    jsonResponse([
        'success' => true,
        'message' => 'Veškerá vaše data byla nenávratně smazána dle GDPR (právo na výmaz).',
    ]);
}

/**
 * GDPR Data portability - export ALL user data as JSON
 */
function handleExportData(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Metoda není povolena.', 405);
    }

    $userId = requireAuth();
    $db = getDB();

    $usersTable = T('users');
    $dogsTable = T('dogs');
    $availTable = T('availability');
    $matchesTable = T('matches');
    $messagesTable = T('messages');
    $consentsTable = T('gdpr_consents');

    // User profile
    $stmt = $db->prepare("SELECT id, name, age, city, bio, avatar, email, is_available_today, latitude, longitude, last_location_update, rating, rating_count, created_at FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) jsonError('Uživatel nenalezen.', 404);

    // Dogs
    $stmt = $db->prepare("SELECT id, name, breed, size, personality, photo, walk_distance FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['dogs'] = $stmt->fetchAll();

    // Availability
    $stmt = $db->prepare("SELECT time_slot FROM `$availTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['availability'] = array_column($stmt->fetchAll(), 'time_slot');

    // Matches
    $stmt = $db->prepare("
        SELECT m.id, m.status, m.created_at, u.name AS partner_name
        FROM `$matchesTable` m
        JOIN `$usersTable` u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
        WHERE m.user1_id = ? OR m.user2_id = ?
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $user['matches'] = $stmt->fetchAll();

    // All messages (sent AND received)
    $stmt = $db->prepare("
        SELECT msg.content, msg.created_at, msg.sender_id, m.id AS match_id
        FROM `$messagesTable` msg
        JOIN `$matchesTable` m ON m.id = msg.match_id
        WHERE m.user1_id = ? OR m.user2_id = ?
        ORDER BY msg.created_at ASC
    ");
    $stmt->execute([$userId, $userId]);
    $user['messages'] = $stmt->fetchAll();

    // GDPR consents
    $stmt = $db->prepare("SELECT consent_type, consented, ip_address, consented_at FROM `$consentsTable` WHERE user_id = ? ORDER BY consented_at ASC");
    $stmt->execute([$userId]);
    $user['gdpr_consents'] = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'export' => $user,
        'exported_at' => date('Y-m-d H:i:s'),
        'data_controller' => 'MANMAT s.r.o., K Drubezarne 220, 549 54 Police nad Metuji, ICO: 03166236',
        'contact' => 'formanek@manmat.cz',
        'gdpr_note' => 'Export vsech vasich osobnich udaju dle cl. 20 GDPR (pravo na prenositelnost udaju).',
    ]);
}

/**
 * Get user's consent history
 */
function handleGetConsents(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Metoda není povolena.', 405);
    }

    $userId = requireAuth();
    $db = getDB();
    $consentsTable = T('gdpr_consents');

    $stmt = $db->prepare("SELECT id, consent_type, consented, ip_address, consented_at FROM `$consentsTable` WHERE user_id = ? ORDER BY consented_at DESC");
    $stmt->execute([$userId]);
    $consents = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'consents' => $consents,
    ]);
}

/**
 * Withdraw specific consent
 * If core consent (terms/privacy/location) is withdrawn, triggers account deletion
 */
function handleWithdrawConsent(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda není povolena.', 405);
    }

    $userId = requireAuth();
    $db = getDB();

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $consentType = sanitize($data['consent_type'] ?? '');
    $validTypes = ['terms', 'privacy', 'location', 'photos', 'marketing'];

    if (!in_array($consentType, $validTypes)) {
        jsonError('Neplatný typ souhlasu.');
    }

    $consentsTable = T('gdpr_consents');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Log the withdrawal
    $stmt = $db->prepare("INSERT INTO `$consentsTable` (user_id, consent_type, consented, ip_address) VALUES (?, ?, 0, ?)");
    $stmt->execute([$userId, $consentType, $ip]);

    // Core consents - withdrawal means the service cannot function, must delete account
    $coreConsents = ['terms', 'privacy', 'location'];
    if (in_array($consentType, $coreConsents)) {
        jsonResponse([
            'success' => true,
            'requires_deletion' => true,
            'message' => 'Odvolání tohoto souhlasu vyžaduje smazání účtu, protože bez něj nemůže služba fungovat. Potvrďte prosím smazání účtu.',
        ]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Souhlas "' . $consentType . '" byl odvolán.',
    ]);
}
