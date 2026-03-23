<?php
/**
 * DogDate API - Messages
 */

require_once __DIR__ . '/config.php';

$userId = requireAuth();
$db = getDB();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (!empty($_GET['conversations'])) {
            listConversations($db, $userId);
        } else {
            getMessages($db, $userId);
        }
        break;
    case 'POST':
        sendMessage($db, $userId);
        break;
    default:
        jsonError('Metoda není povolena.', 405);
}

function listConversations(PDO $db, int $userId): void {
    // Get all matches with last message
    $stmt = $db->prepare("
        SELECT m.id AS match_id, m.user1_id, m.user2_id, m.status,
               u1.name AS user1_name, u1.avatar AS user1_avatar,
               u2.name AS user2_name, u2.avatar AS user2_avatar,
               (SELECT content FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.created_at DESC LIMIT 1) AS last_message,
               (SELECT created_at FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.created_at DESC LIMIT 1) AS last_message_at,
               (SELECT COUNT(*) FROM messages msg WHERE msg.match_id = m.id) AS message_count
        FROM matches m
        JOIN users u1 ON u1.id = m.user1_id
        JOIN users u2 ON u2.id = m.user2_id
        WHERE (m.user1_id = ? OR m.user2_id = ?)
        ORDER BY last_message_at DESC NULLS LAST, m.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $conversations = $stmt->fetchAll();

    $result = [];
    foreach ($conversations as $conv) {
        $partnerId = ($conv['user1_id'] == $userId) ? $conv['user2_id'] : $conv['user1_id'];
        $partnerName = ($conv['user1_id'] == $userId) ? $conv['user2_name'] : $conv['user1_name'];
        $partnerAvatar = ($conv['user1_id'] == $userId) ? $conv['user2_avatar'] : $conv['user1_avatar'];

        // Get partner initials
        $parts = explode(' ', $partnerName);
        $initials = '';
        foreach ($parts as $part) {
            if (!empty($part)) $initials .= mb_substr($part, 0, 1);
        }

        // Get partner dog
        $dogStmt = $db->prepare("SELECT name FROM dogs WHERE user_id = ? LIMIT 1");
        $dogStmt->execute([$partnerId]);
        $dog = $dogStmt->fetch();

        $result[] = [
            'match_id' => $conv['match_id'],
            'partner_id' => $partnerId,
            'partner_name' => $partnerName,
            'partner_avatar' => $partnerAvatar,
            'partner_initials' => mb_strtoupper(mb_substr($initials, 0, 2)),
            'partner_dog' => $dog ? $dog['name'] : null,
            'status' => $conv['status'],
            'last_message' => $conv['last_message'],
            'last_message_at' => $conv['last_message_at'],
            'message_count' => (int)$conv['message_count'],
        ];
    }

    jsonResponse(['success' => true, 'conversations' => $result]);
}

function getMessages(PDO $db, int $userId): void {
    $matchId = (int)($_GET['match_id'] ?? 0);
    if ($matchId <= 0) jsonError('Chybí ID matche.');

    // Verify user is part of this match
    $stmt = $db->prepare("SELECT id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$matchId, $userId, $userId]);
    if (!$stmt->fetch()) {
        jsonError('Match nebyl nalezen.', 404);
    }

    // Pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE match_id = ?");
    $countStmt->execute([$matchId]);
    $total = (int)$countStmt->fetchColumn();

    // Get messages
    $stmt = $db->prepare("
        SELECT msg.id, msg.sender_id, msg.content, msg.created_at,
               u.name AS sender_name
        FROM messages msg
        JOIN users u ON u.id = msg.sender_id
        WHERE msg.match_id = ?
        ORDER BY msg.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$matchId, $limit, $offset]);
    $messages = $stmt->fetchAll();

    // Mark which are mine
    foreach ($messages as &$msg) {
        $msg['is_mine'] = ($msg['sender_id'] == $userId);
    }
    unset($msg);

    jsonResponse([
        'success' => true,
        'messages' => $messages,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ],
    ]);
}

function sendMessage(PDO $db, int $userId): void {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $matchId = (int)($data['match_id'] ?? $_GET['match_id'] ?? 0);
    $content = sanitize($data['content'] ?? '');

    if ($matchId <= 0) jsonError('Chybí ID matche.');
    if (empty($content)) jsonError('Zpráva nesmí být prázdná.');
    if (mb_strlen($content) > 2000) jsonError('Zpráva je příliš dlouhá (max 2000 znaků).');

    // Verify user is part of this match
    $stmt = $db->prepare("SELECT id, status FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$matchId, $userId, $userId]);
    $match = $stmt->fetch();

    if (!$match) {
        jsonError('Match nebyl nalezen.', 404);
    }

    $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$matchId, $userId, $content]);
    $messageId = (int)$db->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'match_id' => $matchId,
            'sender_id' => $userId,
            'content' => $content,
            'is_mine' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}
