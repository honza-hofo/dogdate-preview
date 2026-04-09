<?php
/**
 * DogDate API - Profiles listing
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metoda není povolena.', 405);
}

$db = getDB();
$currentUserId = getAuthUserId();

$usersTable = T('users');
$dogsTable = T('dogs');
$availTable = T('availability');

// Single profile detail
if (isset($_GET['id'])) {
    $profileId = (int)$_GET['id'];
    $profile = getFullProfile($db, $profileId);
    if (!$profile) {
        jsonError('Profil nebyl nalezen.', 404);
    }
    jsonResponse(['success' => true, 'profile' => $profile]);
}

// List profiles with filters
$where = [];
$params = [];

// Exclude current user
if ($currentUserId > 0) {
    $where[] = "u.id != ?";
    $params[] = $currentUserId;
}

// Filter by dog size
if (!empty($_GET['size']) && in_array($_GET['size'], ['maly', 'stredni', 'velky'])) {
    $where[] = "u.id IN (SELECT user_id FROM `$dogsTable` WHERE size = ?)";
    $params[] = $_GET['size'];
}

// Filter by personality
if (!empty($_GET['personality']) && in_array($_GET['personality'], ['hravy', 'klidny', 'smisena'])) {
    $where[] = "u.id IN (SELECT user_id FROM `$dogsTable` WHERE personality = ?)";
    $params[] = $_GET['personality'];
}

// Filter by available today
if (!empty($_GET['available']) && $_GET['available'] === '1') {
    $where[] = "u.is_available_today = 1";
}

// Filter by max walk distance
if (!empty($_GET['max_distance'])) {
    $maxDist = (int)$_GET['max_distance'];
    if ($maxDist > 0) {
        $where[] = "u.id IN (SELECT user_id FROM `$dogsTable` WHERE walk_distance <= ?)";
        $params[] = $maxDist;
    }
}

// Filter by city
if (!empty($_GET['city'])) {
    $where[] = "u.city LIKE ?";
    $params[] = '%' . sanitize($_GET['city']) . '%';
}

// Filter by time slot
if (!empty($_GET['time_slot']) && in_array($_GET['time_slot'], ['rano', 'dopoledne', 'odpoledne', 'vecer'])) {
    $where[] = "u.id IN (SELECT user_id FROM `$availTable` WHERE time_slot = ?)";
    $params[] = $_GET['time_slot'];
}

// Geolocation filter - find users within radius (km)
$userLat = null;
$userLng = null;
$geoRadius = null;

if (!empty($_GET['lat']) && !empty($_GET['lng'])) {
    $userLat = (float)$_GET['lat'];
    $userLng = (float)$_GET['lng'];
    $geoRadius = !empty($_GET['radius']) ? (float)$_GET['radius'] : 10; // default 10 km

    // Haversine approximation (filter by bounding box first, then precise distance)
    $latDelta = $geoRadius / 111.0; // ~111 km per degree latitude
    $lngDelta = $geoRadius / (111.0 * cos(deg2rad($userLat)));

    $where[] = "u.latitude IS NOT NULL AND u.longitude IS NOT NULL";
    $where[] = "u.latitude BETWEEN ? AND ?";
    $params[] = $userLat - $latDelta;
    $params[] = $userLat + $latDelta;
    $where[] = "u.longitude BETWEEN ? AND ?";
    $params[] = $userLng - $lngDelta;
    $params[] = $userLng + $lngDelta;
} elseif ($currentUserId > 0) {
    // Use current user's location if available
    $locStmt = $db->prepare("SELECT latitude, longitude FROM `$usersTable` WHERE id = ? AND latitude IS NOT NULL");
    $locStmt->execute([$currentUserId]);
    $loc = $locStmt->fetch();
    if ($loc) {
        $userLat = (float)$loc['latitude'];
        $userLng = (float)$loc['longitude'];
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM `$usersTable` u $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Get users - include lat/lng for distance calculation
$sql = "SELECT u.id, u.name, u.age, u.city, u.bio, u.avatar, u.is_available_today, u.latitude, u.longitude, u.rating, u.rating_count, u.created_at
        FROM `$usersTable` u
        $whereClause
        ORDER BY u.is_available_today DESC, u.rating DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Enrich with dogs, availability and distance
$profiles = [];
foreach ($users as $user) {
    $profile = enrichProfile($db, $user);
    // Calculate distance if we have coordinates
    if ($userLat && $userLng && !empty($user['latitude']) && !empty($user['longitude'])) {
        $profile['distance_km'] = round(haversineDistance($userLat, $userLng, (float)$user['latitude'], (float)$user['longitude']), 1);
    } else {
        $profile['distance_km'] = null;
    }
    $profile['latitude'] = $user['latitude'] ?? null;
    $profile['longitude'] = $user['longitude'] ?? null;
    $profiles[] = $profile;
}

// Sort by distance if geo search
if ($userLat && $userLng) {
    usort($profiles, function($a, $b) {
        if ($a['distance_km'] === null) return 1;
        if ($b['distance_km'] === null) return -1;
        return $a['distance_km'] <=> $b['distance_km'];
    });
}

jsonResponse([
    'success' => true,
    'profiles' => $profiles,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit),
    ],
]);

function getFullProfile(PDO $db, int $id): ?array {
    $usersTable = T('users');
    $stmt = $db->prepare("SELECT id, name, age, city, bio, avatar, is_available_today, rating, rating_count, created_at FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) return null;
    return enrichProfile($db, $user);
}

function enrichProfile(PDO $db, array $user): array {
    $userId = $user['id'];
    $dogsTable = T('dogs');
    $availTable = T('availability');

    // Dogs
    $stmt = $db->prepare("SELECT id, name, breed, size, personality, photo, walk_distance FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['dogs'] = $stmt->fetchAll();

    // Availability
    $stmt = $db->prepare("SELECT time_slot FROM `$availTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['availability'] = array_column($stmt->fetchAll(), 'time_slot');

    // Initials
    $parts = explode(' ', $user['name']);
    $user['initials'] = '';
    foreach ($parts as $part) {
        if (!empty($part)) $user['initials'] .= mb_substr($part, 0, 1);
    }
    $user['initials'] = mb_strtoupper(mb_substr($user['initials'], 0, 2));

    // Size/personality labels
    $sizeLabels = ['maly' => 'Malý', 'stredni' => 'Střední', 'velky' => 'Velký'];
    $personalityLabels = ['hravy' => 'Hravý', 'klidny' => 'Klidný', 'smisena' => 'Smíšená'];
    $timeLabels = ['rano' => 'Ráno', 'dopoledne' => 'Dopoledne', 'odpoledne' => 'Odpoledne', 'vecer' => 'Večer'];

    foreach ($user['dogs'] as &$dog) {
        $dog['size_label'] = $sizeLabels[$dog['size']] ?? $dog['size'];
        $dog['personality_label'] = $personalityLabels[$dog['personality']] ?? $dog['personality'];
    }
    unset($dog);

    $user['availability_labels'] = array_map(function($slot) use ($timeLabels) {
        return $timeLabels[$slot] ?? $slot;
    }, $user['availability']);

    return $user;
}
