<?php
/**
 * DogDate API - Profile updates
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metoda není povolena.', 405);
}

$userId = requireAuth();
$db = getDB();
$action = $_GET['action'] ?? 'update';

switch ($action) {
    case 'update':
        updateProfile($db, $userId);
        break;
    case 'toggle_available':
        toggleAvailable($db, $userId);
        break;
    case 'update_location':
        updateLocation($db, $userId);
        break;
    case 'upload_avatar':
        uploadAvatar($db, $userId);
        break;
    case 'upload_dog_photo':
        uploadDogPhoto($db, $userId);
        break;
    case 'update_dog':
        updateDog($db, $userId);
        break;
    case 'update_availability':
        updateAvailability($db, $userId);
        break;
    case 'delete_account':
        deleteAccount($db, $userId);
        break;
    case 'export_data':
        exportData($db, $userId);
        break;
    default:
        jsonError('Neznámá akce.', 400);
}

function updateProfile(PDO $db, int $userId): void {
    $usersTable = T('users');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $fields = [];
    $params = [];

    if (isset($data['name'])) {
        $name = sanitize($data['name']);
        if (empty($name)) jsonError('Jméno nesmí být prázdné.');
        $fields[] = 'name = ?';
        $params[] = $name;
    }

    if (isset($data['age'])) {
        $age = (int)$data['age'];
        if ($age < 18 || $age > 120) jsonError('Zadejte platný věk (18-120).');
        $fields[] = 'age = ?';
        $params[] = $age;
    }

    if (isset($data['city'])) {
        $fields[] = 'city = ?';
        $params[] = sanitize($data['city']);
    }

    if (isset($data['bio'])) {
        $bio = sanitize($data['bio']);
        if (mb_strlen($bio) > 500) jsonError('Bio je příliš dlouhé (max 500 znaků).');
        $fields[] = 'bio = ?';
        $params[] = $bio;
    }

    if (isset($data['is_available_today'])) {
        $fields[] = 'is_available_today = ?';
        $params[] = $data['is_available_today'] ? 1 : 0;
    }

    if (empty($fields)) {
        jsonError('Žádná data k aktualizaci.');
    }

    $params[] = $userId;
    $sql = "UPDATE `$usersTable` SET " . implode(', ', $fields) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    // Return updated profile
    require_once __DIR__ . '/auth.php';
    jsonResponse([
        'success' => true,
        'message' => 'Profil aktualizován.',
        'user' => getUserProfile($userId),
    ]);
}

function toggleAvailable(PDO $db, int $userId): void {
    $usersTable = T('users');

    $stmt = $db->prepare("SELECT is_available_today FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $current = (int)$stmt->fetchColumn();

    $new = $current ? 0 : 1;
    $db->prepare("UPDATE `$usersTable` SET is_available_today = ? WHERE id = ?")->execute([$new, $userId]);

    jsonResponse([
        'success' => true,
        'is_available_today' => (bool)$new,
        'message' => $new ? 'Jste nyní dostupný/á!' : 'Dostupnost vypnuta.',
    ]);
}

function updateLocation(PDO $db, int $userId): void {
    $usersTable = T('users');

    $data = getJsonBody();
    $lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
    $lng = isset($data['longitude']) ? (float)$data['longitude'] : null;

    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonError('Neplatné souřadnice.');
    }

    // GDPR: Round to ~1km precision (2 decimal places) to protect privacy
    $lat = round($lat, 2);
    $lng = round($lng, 2);

    $nowExpr = (DOGDATE_DB_MODE === 'mysql') ? 'NOW()' : "datetime('now')";
    $db->prepare("UPDATE `$usersTable` SET latitude = ?, longitude = ?, last_location_update = $nowExpr WHERE id = ?")
       ->execute([$lat, $lng, $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Poloha aktualizována.',
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

function uploadAvatar(PDO $db, int $userId): void {
    $usersTable = T('users');

    if (empty($_FILES['avatar'])) {
        jsonError('Žádný soubor nebyl nahrán.');
    }

    require_once __DIR__ . '/upload.php';
    $url = processUpload($_FILES['avatar']);

    // Delete old avatar file
    $stmt = $db->prepare("SELECT avatar FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();
    if ($oldAvatar) {
        $oldPath = __DIR__ . '/..' . $oldAvatar;
        if (file_exists($oldPath)) unlink($oldPath);
    }

    $db->prepare("UPDATE `$usersTable` SET avatar = ? WHERE id = ?")->execute([$url, $userId]);

    jsonResponse([
        'success' => true,
        'avatar' => $url,
        'message' => 'Avatar aktualizován.',
    ]);
}

function uploadDogPhoto(PDO $db, int $userId): void {
    $dogsTable = T('dogs');

    if (empty($_FILES['photo'])) {
        jsonError('Žádný soubor nebyl nahrán.');
    }

    $dogId = (int)($_POST['dog_id'] ?? $_GET['dog_id'] ?? 0);
    if ($dogId <= 0) jsonError('Chybí ID psa.');

    // Verify dog belongs to user
    $stmt = $db->prepare("SELECT id, photo FROM `$dogsTable` WHERE id = ? AND user_id = ?");
    $stmt->execute([$dogId, $userId]);
    $dog = $stmt->fetch();
    if (!$dog) jsonError('Pes nebyl nalezen.', 404);

    require_once __DIR__ . '/upload.php';
    $url = processUpload($_FILES['photo']);

    // Delete old photo
    if ($dog['photo']) {
        $oldPath = __DIR__ . '/..' . $dog['photo'];
        if (file_exists($oldPath)) unlink($oldPath);
    }

    $db->prepare("UPDATE `$dogsTable` SET photo = ? WHERE id = ?")->execute([$url, $dogId]);

    jsonResponse([
        'success' => true,
        'photo' => $url,
        'message' => 'Fotka psa aktualizována.',
    ]);
}

function updateDog(PDO $db, int $userId): void {
    $dogsTable = T('dogs');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $dogId = (int)($data['dog_id'] ?? $_GET['dog_id'] ?? 0);

    if ($dogId > 0) {
        // Update existing dog
        $stmt = $db->prepare("SELECT id FROM `$dogsTable` WHERE id = ? AND user_id = ?");
        $stmt->execute([$dogId, $userId]);
        if (!$stmt->fetch()) jsonError('Pes nebyl nalezen.', 404);

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $name = sanitize($data['name']);
            if (empty($name)) jsonError('Jméno psa nesmí být prázdné.');
            $fields[] = 'name = ?';
            $params[] = $name;
        }
        if (isset($data['breed'])) { $fields[] = 'breed = ?'; $params[] = sanitize($data['breed']); }
        if (isset($data['size']) && in_array($data['size'], ['maly', 'stredni', 'velky'])) {
            $fields[] = 'size = ?'; $params[] = $data['size'];
        }
        if (isset($data['personality']) && in_array($data['personality'], ['hravy', 'klidny', 'smisena'])) {
            $fields[] = 'personality = ?'; $params[] = $data['personality'];
        }
        if (isset($data['walk_distance'])) {
            $dist = max(1, min(50, (int)$data['walk_distance']));
            $fields[] = 'walk_distance = ?'; $params[] = $dist;
        }

        if (!empty($fields)) {
            $params[] = $dogId;
            $db->prepare("UPDATE `$dogsTable` SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
    } else {
        // Add new dog
        $name = sanitize($data['name'] ?? '');
        if (empty($name)) jsonError('Jméno psa je povinné.');

        $breed = sanitize($data['breed'] ?? '');
        $size = in_array($data['size'] ?? '', ['maly', 'stredni', 'velky']) ? $data['size'] : 'stredni';
        $personality = in_array($data['personality'] ?? '', ['hravy', 'klidny', 'smisena']) ? $data['personality'] : 'smisena';
        $walkDist = max(1, min(50, (int)($data['walk_distance'] ?? 5)));

        $stmt = $db->prepare("INSERT INTO `$dogsTable` (user_id, name, breed, size, personality, walk_distance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $breed, $size, $personality, $walkDist]);
    }

    jsonResponse(['success' => true, 'message' => 'Informace o psovi aktualizovány.']);
}

function updateAvailability(PDO $db, int $userId): void {
    $availTable = T('availability');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $timeSlots = $data['availability'] ?? [];
    if (is_string($timeSlots)) $timeSlots = explode(',', $timeSlots);

    $validSlots = ['rano', 'dopoledne', 'odpoledne', 'vecer'];

    // Remove existing
    $db->prepare("DELETE FROM `$availTable` WHERE user_id = ?")->execute([$userId]);

    // Insert new
    $stmtAvail = $db->prepare("INSERT INTO `$availTable` (user_id, time_slot) VALUES (?, ?)");
    foreach ($timeSlots as $slot) {
        $slot = trim($slot);
        if (in_array($slot, $validSlots)) {
            $stmtAvail->execute([$userId, $slot]);
        }
    }

    jsonResponse(['success' => true, 'message' => 'Dostupnost aktualizována.']);
}

function deleteAccount(PDO $db, int $userId): void {
    $usersTable = T('users');
    $dogsTable = T('dogs');
    $availTable = T('availability');
    $matchesTable = T('matches');
    $messagesTable = T('messages');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    // Require password confirmation
    $password = $data['password'] ?? '';
    if (empty($password)) jsonError('Pro smazání účtu zadejte heslo.');

    $stmt = $db->prepare("SELECT password FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($password, $hash)) {
        jsonError('Nesprávné heslo.', 401);
    }

    // Delete avatar and dog photos
    $stmt = $db->prepare("SELECT avatar FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $avatar = $stmt->fetchColumn();
    if ($avatar) {
        $path = __DIR__ . '/..' . $avatar;
        if (file_exists($path)) unlink($path);
    }

    $stmt = $db->prepare("SELECT photo FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($photo = $stmt->fetchColumn()) {
        if ($photo) {
            $path = __DIR__ . '/..' . $photo;
            if (file_exists($path)) unlink($path);
        }
    }

    $consentsTable = T('gdpr_consents');

    // Delete all user data (CASCADE handles related tables)
    $db->prepare("DELETE FROM `$messagesTable` WHERE sender_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM `$matchesTable` WHERE user1_id = ? OR user2_id = ?")->execute([$userId, $userId]);
    $db->prepare("DELETE FROM `$availTable` WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM `$dogsTable` WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM `$consentsTable` WHERE user_id = ?")->execute([$userId]);
    $db->prepare("DELETE FROM `$usersTable` WHERE id = ?")->execute([$userId]);

    session_destroy();

    jsonResponse(['success' => true, 'message' => 'Účet byl smazán.']);
}

function exportData(PDO $db, int $userId): void {
    $usersTable = T('users');
    $dogsTable = T('dogs');
    $availTable = T('availability');
    $matchesTable = T('matches');
    $messagesTable = T('messages');
    $consentsTable = T('gdpr_consents');

    // Get all user data
    $stmt = $db->prepare("SELECT id, name, age, city, bio, avatar, email, is_available_today, latitude, longitude, last_location_update, rating, rating_count, created_at FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $stmt = $db->prepare("SELECT name, breed, size, personality, photo, walk_distance FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['dogs'] = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT time_slot FROM `$availTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['availability'] = array_column($stmt->fetchAll(), 'time_slot');

    // Get matches
    $stmt = $db->prepare("
        SELECT m.id, m.status, m.created_at, u.name AS partner_name
        FROM `$matchesTable` m
        JOIN `$usersTable` u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
        WHERE m.user1_id = ? OR m.user2_id = ?
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $user['matches'] = $stmt->fetchAll();

    // Get messages
    $stmt = $db->prepare("
        SELECT msg.content, msg.created_at, m.id AS match_id
        FROM `$messagesTable` msg
        JOIN `$matchesTable` m ON m.id = msg.match_id
        WHERE msg.sender_id = ?
        ORDER BY msg.created_at ASC
    ");
    $stmt->execute([$userId]);
    $user['sent_messages'] = $stmt->fetchAll();

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
    ]);
}
