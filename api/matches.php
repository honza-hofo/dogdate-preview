<?php
/**
 * DogDate API - Matches
 */

require_once __DIR__ . '/config.php';

$userId = requireAuth();
$db = getDB();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        listMatches($db, $userId);
        break;
    case 'POST':
        switch ($action) {
            case 'create':
                createMatch($db, $userId);
                break;
            case 'confirm':
                updateMatchStatus($db, $userId, 'confirmed');
                break;
            case 'plan':
                updateMatchStatus($db, $userId, 'walk_planned');
                break;
            default:
                jsonError('Neznámá akce.', 400);
        }
        break;
    case 'DELETE':
        deleteMatch($db, $userId);
        break;
    default:
        jsonError('Metoda není povolena.', 405);
}

function listMatches(PDO $db, int $userId): void {
    $matchesTable = T('matches');
    $usersTable = T('users');
    $dogsTable = T('dogs');

    $stmt = $db->prepare("
        SELECT m.id, m.user1_id, m.user2_id, m.status, m.created_at,
               u1.name AS user1_name, u1.avatar AS user1_avatar, u1.city AS user1_city,
               u2.name AS user2_name, u2.avatar AS user2_avatar, u2.city AS user2_city
        FROM `$matchesTable` m
        JOIN `$usersTable` u1 ON u1.id = m.user1_id
        JOIN `$usersTable` u2 ON u2.id = m.user2_id
        WHERE m.user1_id = ? OR m.user2_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $matches = $stmt->fetchAll();

    // Enrich with partner info and dogs
    $result = [];
    foreach ($matches as $match) {
        $partnerId = ($match['user1_id'] == $userId) ? $match['user2_id'] : $match['user1_id'];
        $partnerName = ($match['user1_id'] == $userId) ? $match['user2_name'] : $match['user1_name'];
        $partnerAvatar = ($match['user1_id'] == $userId) ? $match['user2_avatar'] : $match['user1_avatar'];
        $partnerCity = ($match['user1_id'] == $userId) ? $match['user2_city'] : $match['user1_city'];

        // Get partner's dog
        $dogStmt = $db->prepare("SELECT name, breed FROM `$dogsTable` WHERE user_id = ? LIMIT 1");
        $dogStmt->execute([$partnerId]);
        $dog = $dogStmt->fetch();

        // Get partner initials
        $parts = explode(' ', $partnerName);
        $initials = '';
        foreach ($parts as $part) {
            if (!empty($part)) $initials .= mb_substr($part, 0, 1);
        }

        $result[] = [
            'id' => $match['id'],
            'partner_id' => $partnerId,
            'partner_name' => $partnerName,
            'partner_avatar' => $partnerAvatar,
            'partner_city' => $partnerCity,
            'partner_initials' => mb_strtoupper(mb_substr($initials, 0, 2)),
            'partner_dog' => $dog ? $dog['name'] : null,
            'partner_breed' => $dog ? $dog['breed'] : null,
            'status' => $match['status'],
            'is_initiator' => ($match['user1_id'] == $userId),
            'created_at' => $match['created_at'],
        ];
    }

    jsonResponse(['success' => true, 'matches' => $result]);
}

function createMatch(PDO $db, int $userId): void {
    $matchesTable = T('matches');
    $usersTable = T('users');

    $targetId = (int)($_GET['user_id'] ?? 0);
    if ($targetId <= 0) {
        // Also check POST body
        $data = getJsonBody();
        $targetId = (int)($data['user_id'] ?? 0);
    }

    if ($targetId <= 0) jsonError('Chybí ID uživatele.');
    if ($targetId === $userId) jsonError('Nemůžete matchovat sám/sama sebe.');

    // Check target exists
    $stmt = $db->prepare("SELECT id FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$targetId]);
    if (!$stmt->fetch()) {
        jsonError('Uživatel nebyl nalezen.', 404);
    }

    // Check if match already exists
    $stmt = $db->prepare("
        SELECT id FROM `$matchesTable`
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    if ($stmt->fetch()) {
        jsonError('Match již existuje.');
    }

    $stmt = $db->prepare("INSERT INTO `$matchesTable` (user1_id, user2_id) VALUES (?, ?)");
    $stmt->execute([$userId, $targetId]);
    $matchId = (int)$db->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Match vytvořen!',
        'match_id' => $matchId,
    ], 201);
}

function updateMatchStatus(PDO $db, int $userId, string $newStatus): void {
    $matchesTable = T('matches');

    $matchId = (int)($_GET['match_id'] ?? 0);
    if ($matchId <= 0) {
        $data = getJsonBody();
        $matchId = (int)($data['match_id'] ?? 0);
    }

    if ($matchId <= 0) jsonError('Chybí ID matche.');

    // Verify user is part of this match
    $stmt = $db->prepare("SELECT id, status FROM `$matchesTable` WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$matchId, $userId, $userId]);
    $match = $stmt->fetch();

    if (!$match) {
        jsonError('Match nebyl nalezen.', 404);
    }

    $stmt = $db->prepare("UPDATE `$matchesTable` SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $matchId]);

    $statusLabels = ['confirmed' => 'potvrzen', 'walk_planned' => 'procházka naplánována'];
    jsonResponse([
        'success' => true,
        'message' => 'Match ' . ($statusLabels[$newStatus] ?? $newStatus) . '!',
    ]);
}

function deleteMatch(PDO $db, int $userId): void {
    $matchesTable = T('matches');
    $messagesTable = T('messages');

    $matchId = (int)($_GET['match_id'] ?? 0);
    if ($matchId <= 0) jsonError('Chybí ID matche.');

    // Verify user is part of this match
    $stmt = $db->prepare("SELECT id FROM `$matchesTable` WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$matchId, $userId, $userId]);
    if (!$stmt->fetch()) {
        jsonError('Match nebyl nalezen.', 404);
    }

    // Delete associated messages first
    $db->prepare("DELETE FROM `$messagesTable` WHERE match_id = ?")->execute([$matchId]);
    $db->prepare("DELETE FROM `$matchesTable` WHERE id = ?")->execute([$matchId]);

    jsonResponse(['success' => true, 'message' => 'Match byl odstraněn.']);
}
