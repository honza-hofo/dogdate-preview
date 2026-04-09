<?php
/**
 * DogDate API - Authentication
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'me':
        handleMe();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        jsonError('Neznámá akce.', 400);
}

function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda není povolena.', 405);
    }

    // Accept both JSON and form data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    // Validate required fields
    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $age = (int)($data['age'] ?? 0);
    $city = sanitize($data['city'] ?? '');
    $bio = sanitize($data['bio'] ?? '');

    if (empty($name)) jsonError('Jméno je povinné.');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Zadejte platný email.');
    if (strlen($password) < 6) jsonError('Heslo musí mít alespoň 6 znaků.');
    if ($age < 18 || $age > 120) jsonError('Služba DogDate je určena osobám starším 18 let.');
    if (empty($city)) jsonError('Město je povinné.');

    // GDPR consent validation
    $consentTerms = !empty($data['consent_terms']);
    $consentPrivacy = !empty($data['consent_privacy']);
    $consentLocation = !empty($data['consent_location']);
    $consentPhotos = !empty($data['consent_photos']);

    if (!$consentTerms || !$consentPrivacy) {
        jsonError('Pro registraci musíte souhlasit s podmínkami používání a zásadami ochrany osobních údajů.');
    }
    if (!$consentLocation) {
        jsonError('Pro fungování služby je nutný souhlas se zpracováním údajů o poloze.');
    }

    $db = getDB();
    $usersTable = T('users');
    $dogsTable = T('dogs');
    $availTable = T('availability');
    $consentsTable = T('gdpr_consents');

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM `$usersTable` WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonError('Tento email je již zaregistrován.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Handle avatar upload
    $avatarUrl = null;
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/upload.php';
        $avatarUrl = processUpload($_FILES['avatar']);
    }

    $db->beginTransaction();

    try {
        // Insert user
        $stmt = $db->prepare("INSERT INTO `$usersTable` (name, age, city, bio, avatar, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $age, $city, $bio, $avatarUrl, $email, $hashedPassword]);
        $userId = (int)$db->lastInsertId();

        // Store GDPR consents
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmtConsent = $db->prepare("INSERT INTO `$consentsTable` (user_id, consent_type, consented, ip_address) VALUES (?, ?, ?, ?)");
        $stmtConsent->execute([$userId, 'terms', 1, $ip]);
        $stmtConsent->execute([$userId, 'privacy', 1, $ip]);
        $stmtConsent->execute([$userId, 'location', 1, $ip]);
        $stmtConsent->execute([$userId, 'photos', $consentPhotos ? 1 : 0, $ip]);

        // Insert dog if provided
        $dogName = sanitize($data['dog_name'] ?? '');
        if (!empty($dogName)) {
            $dogBreed = sanitize($data['dog_breed'] ?? '');
            $dogSize = $data['dog_size'] ?? 'stredni';
            $dogPersonality = $data['dog_personality'] ?? 'smisena';
            $dogWalkDistance = (int)($data['dog_walk_distance'] ?? 5);

            if (!in_array($dogSize, ['maly', 'stredni', 'velky'])) $dogSize = 'stredni';
            if (!in_array($dogPersonality, ['hravy', 'klidny', 'smisena'])) $dogPersonality = 'smisena';
            if ($dogWalkDistance < 1) $dogWalkDistance = 1;
            if ($dogWalkDistance > 50) $dogWalkDistance = 50;

            // Handle dog photo upload
            $dogPhotoUrl = null;
            if (!empty($_FILES['dog_photo']) && $_FILES['dog_photo']['error'] === UPLOAD_ERR_OK) {
                require_once __DIR__ . '/upload.php';
                $dogPhotoUrl = processUpload($_FILES['dog_photo']);
            }

            $stmt = $db->prepare("INSERT INTO `$dogsTable` (user_id, name, breed, size, personality, photo, walk_distance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $dogName, $dogBreed, $dogSize, $dogPersonality, $dogPhotoUrl, $dogWalkDistance]);
        }

        // Insert availability
        $timeSlots = $data['availability'] ?? [];
        if (is_string($timeSlots)) $timeSlots = explode(',', $timeSlots);
        $validSlots = ['rano', 'dopoledne', 'odpoledne', 'vecer'];
        $stmtAvail = $db->prepare("INSERT INTO `$availTable` (user_id, time_slot) VALUES (?, ?)");
        foreach ($timeSlots as $slot) {
            $slot = trim($slot);
            if (in_array($slot, $validSlots)) {
                $stmtAvail->execute([$userId, $slot]);
            }
        }

        $db->commit();

        // Auto-login after registration
        $_SESSION['user_id'] = $userId;

        // Send welcome email (only in WordPress context where wp_mail exists)
        if (function_exists('wp_mail')) {
            $dogInfo = !empty($dogName) ? "$dogName ($dogBreed)" : '';
            $emailBody = "Ahoj $name!\n\n";
            $emailBody .= "Vítej v DogDate – psí seznamce na MakejsMANMATem.cz!\n\n";
            $emailBody .= "Tvůj účet byl úspěšně vytvořen.\n\n";
            $emailBody .= "Tvůj profil:\n";
            $emailBody .= "Jméno: $name\n";
            $emailBody .= "Město: $city\n";
            if ($dogInfo) $emailBody .= "Pes: $dogInfo\n";
            $emailBody .= "\nTeď můžeš procházet profily pejskařů v okolí a najít parťáka na procházku.\n";
            $emailBody .= "Přihlásit se: https://makejsmanmatem.cz/dogdate-app/\n\n";
            $emailBody .= "Přejeme spoustu hezkých procházek!\n";
            $emailBody .= "Tým DogDate · MANMAT s.r.o.\n\n";
            $emailBody .= "---\n";
            $emailBody .= "Pokud jsi se neregistroval/a, tento email ignoruj.\n";
            $emailBody .= "Pro smazání účtu se přihlas a jdi do Profil → Správa osobních údajů.\n";

            wp_mail(
                $email,
                'Vítej v DogDate! Tvůj účet je připravený',
                $emailBody,
                ['From: DogDate <formanek@manmat.cz>', 'Reply-To: formanek@manmat.cz']
            );
        }

        jsonResponse([
            'success' => true,
            'message' => 'Registrace proběhla úspěšně!',
            'user' => getUserProfile($userId),
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Chyba při registraci: ' . $e->getMessage(), 500);
    }
}

function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda není povolena.', 405);
    }

    checkRateLimit('login');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = getJsonBody();
    } else {
        $data = $_POST;
    }

    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonError('Zadejte email a heslo.');
    }

    $db = getDB();
    $usersTable = T('users');
    $stmt = $db->prepare("SELECT id, password FROM `$usersTable` WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonError('Nesprávný email nebo heslo.', 401);
    }

    $_SESSION['user_id'] = (int)$user['id'];

    jsonResponse([
        'success' => true,
        'message' => 'Přihlášení úspěšné!',
        'user' => getUserProfile((int)$user['id']),
    ]);
}

function handleMe(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Metoda není povolena.', 405);
    }

    $userId = requireAuth();
    jsonResponse(['success' => true, 'user' => getUserProfile($userId)]);
}

function handleLogout(): void {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Odhlášení úspěšné.']);
}

/**
 * Get full user profile with dogs and availability
 */
function getUserProfile(int $userId): ?array {
    $db = getDB();
    $usersTable = T('users');
    $dogsTable = T('dogs');
    $availTable = T('availability');

    $stmt = $db->prepare("SELECT id, name, age, city, bio, avatar, email, is_available_today, rating, rating_count, created_at FROM `$usersTable` WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return null;

    // Get dogs
    $stmt = $db->prepare("SELECT id, name, breed, size, personality, photo, walk_distance FROM `$dogsTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['dogs'] = $stmt->fetchAll();

    // Get availability
    $stmt = $db->prepare("SELECT time_slot FROM `$availTable` WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user['availability'] = array_column($stmt->fetchAll(), 'time_slot');

    // Generate initials
    $parts = explode(' ', $user['name']);
    $user['initials'] = '';
    foreach ($parts as $part) {
        if (!empty($part)) $user['initials'] .= mb_substr($part, 0, 1);
    }
    $user['initials'] = mb_strtoupper(mb_substr($user['initials'], 0, 2));

    return $user;
}
