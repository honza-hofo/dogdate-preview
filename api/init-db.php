<?php
/**
 * DogDate API - Database initialization & seed data
 */

if (!function_exists('getDB')) {
    require_once __DIR__ . '/config.php';
}

function initDatabase(): void {
    $db = getDB();

    if (DOGDATE_DB_MODE === 'mysql') {
        initMySQL($db);
    } else {
        initSQLite($db);
    }
}

function initMySQL(PDO $db): void {
    $p = DOGDATE_TABLE_PREFIX;

    // Create tables with MySQL syntax
    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}users` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            age INT DEFAULT NULL,
            city VARCHAR(255) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            avatar VARCHAR(500) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL UNIQUE,
            password VARCHAR(255) DEFAULT NULL,
            is_available_today TINYINT(1) DEFAULT 0,
            latitude DOUBLE DEFAULT NULL,
            longitude DOUBLE DEFAULT NULL,
            last_location_update DATETIME DEFAULT NULL,
            rating DOUBLE DEFAULT 0,
            rating_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}dogs` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            breed VARCHAR(255) DEFAULT NULL,
            size ENUM('maly','stredni','velky') DEFAULT 'stredni',
            personality ENUM('hravy','klidny','smisena') DEFAULT 'smisena',
            photo VARCHAR(500) DEFAULT NULL,
            walk_distance INT DEFAULT 5,
            FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}availability` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            time_slot ENUM('rano','dopoledne','odpoledne','vecer') DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}matches` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user1_id INT NOT NULL,
            user2_id INT NOT NULL,
            status ENUM('pending','confirmed','walk_planned') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user1_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE,
            FOREIGN KEY (user2_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}messages` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            sender_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (match_id) REFERENCES `{$p}matches`(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}rate_limits` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `{$p}gdpr_consents` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            consent_type VARCHAR(50) NOT NULL,
            consented TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            consented_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Check if already seeded
    $count = $db->query("SELECT COUNT(*) FROM `{$p}users`")->fetchColumn();
    if ($count > 0) return;

    seedData($db, $p, 'mysql');
}

function initSQLite(PDO $db): void {
    // Create tables with SQLite syntax
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            age INTEGER,
            city TEXT,
            bio TEXT,
            avatar TEXT,
            email TEXT UNIQUE,
            password TEXT,
            is_available_today INTEGER DEFAULT 0,
            latitude REAL DEFAULT NULL,
            longitude REAL DEFAULT NULL,
            last_location_update DATETIME DEFAULT NULL,
            rating REAL DEFAULT 0,
            rating_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS dogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            breed TEXT,
            size TEXT CHECK(size IN ('maly','stredni','velky')),
            personality TEXT CHECK(personality IN ('hravy','klidny','smisena')),
            photo TEXT,
            walk_distance INTEGER DEFAULT 5,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS availability (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            time_slot TEXT CHECK(time_slot IN ('rano','dopoledne','odpoledne','vecer')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user1_id INTEGER NOT NULL,
            user2_id INTEGER NOT NULL,
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending','confirmed','walk_planned')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            action TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS gdpr_consents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            consent_type TEXT NOT NULL,
            consented INTEGER DEFAULT 0,
            ip_address TEXT,
            consented_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    // Check if already seeded
    $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count > 0) return;

    seedData($db, '', 'sqlite');
}

function seedData(PDO $db, string $prefix, string $mode): void {
    $password = password_hash('heslo123', PASSWORD_DEFAULT);

    $users = [
        ['Jana Nováková', 28, 'Praha', 'Miluji procházky po Stromovce a Letné. Hledám parťáky na ranní venčení!', 'jana@example.cz', 1],
        ['Petr Svoboda', 35, 'Praha', 'Běhám se psem každé ráno. Rád potkávám nové lidi i psy.', 'petr@example.cz', 1],
        ['Lucie Veselá', 24, 'Brno', 'Studentka veteriny. Moje Bella potřebuje víc kamarádů na hraní!', 'lucie@example.cz', 0],
        ['Martin Dvořák', 42, 'Plzeň', 'Aktivní táta se psem. Chodíme na túry každý víkend.', 'martin@example.cz', 1],
        ['Kateřina Procházková', 31, 'Praha', 'Freelancerka s flexibilním časem. Chlupáč je můj nejlepší kolega.', 'katerina@example.cz', 1],
        ['Tomáš Krejčí', 29, 'Hradec Králové', 'IT borec co potřebuje víc čerstvého vzduchu. Pomůžete?', 'tomas@example.cz', 1],
        ['Eva Marková', 38, 'Praha', 'Máma dvou dětí a jednoho huňáče. Rádi chodíme do parku.', 'eva@example.cz', 0],
        ['David Černý', 26, 'Olomouc', 'Fotograf a outdoorový nadšenec. Můj pes je nejlepší model.', 'david@example.cz', 1],
        ['Martina Šťastná', 33, 'Praha', 'Jogínka a pejskařka. Ráno jóga, dopoledne procházka s Lolou.', 'martina@example.cz', 1],
        ['Jakub Horák', 45, 'Liberec', 'Chodíme min. 10 km denně. Hledám stejně aktivní parťáky.', 'jakub@example.cz', 0],
        ['Tereza Pokorná', 22, 'České Budějovice', 'Studentka a dobrovolnice v útulku. Psi jsou můj život!', 'tereza@example.cz', 1],
        ['Pavel Novotný', 50, 'Pardubice', 'Stará garda. Chodím s Barym po nábřeží Labe každý den.', 'pavel@example.cz', 1],
    ];

    $dogs = [
        [1, 'Max', 'Labrador', 'velky', 'hravy', 5],
        [2, 'Buddy', 'Border kolie', 'stredni', 'hravy', 8],
        [3, 'Bella', 'Zlatý retrívr', 'velky', 'klidny', 4],
        [4, 'Rex', 'Německý ovčák', 'velky', 'klidny', 10],
        [5, 'Chlupáč', 'Kokršpaněl', 'stredni', 'hravy', 6],
        [6, 'Fík', 'Jack Russell', 'maly', 'hravy', 3],
        [7, 'Teddy', 'Bígl', 'stredni', 'smisena', 4],
        [8, 'Shadow', 'Husky', 'velky', 'hravy', 10],
        [9, 'Lola', 'Maltézák', 'maly', 'klidny', 2],
        [10, 'Argo', 'Rhodéský ridgeback', 'velky', 'klidny', 10],
        [11, 'Daisy', 'Český fousek', 'stredni', 'smisena', 7],
        [12, 'Bary', 'Jezevčík', 'maly', 'klidny', 3],
    ];

    $availability = [
        [1, ['rano', 'odpoledne']],
        [2, ['dopoledne', 'vecer']],
        [3, ['odpoledne']],
        [4, ['rano', 'vecer']],
        [5, ['dopoledne', 'odpoledne']],
        [6, ['rano']],
        [7, ['odpoledne', 'vecer']],
        [8, ['rano', 'dopoledne']],
        [9, ['dopoledne', 'odpoledne']],
        [10, ['vecer']],
        [11, ['rano', 'odpoledne']],
        [12, ['dopoledne', 'vecer']],
    ];

    $ratings = [
        [4.8, 12], [4.5, 8], [4.9, 15], [4.6, 20], [4.7, 10], [4.3, 5],
        [4.8, 18], [4.4, 7], [5.0, 9], [4.2, 25], [4.9, 6], [4.6, 30],
    ];

    $cityCoords = [
        'Praha' => [50.0755, 14.4378],
        'Brno' => [49.1951, 16.6068],
        'Plzeň' => [49.7384, 13.3736],
        'Hradec Králové' => [50.2092, 15.8327],
        'Olomouc' => [49.5938, 17.2509],
        'Liberec' => [50.7663, 15.0543],
        'České Budějovice' => [48.9745, 14.4743],
        'Pardubice' => [50.0343, 15.7812],
    ];

    $usersTable = $prefix ? "`{$prefix}users`" : 'users';
    $dogsTable = $prefix ? "`{$prefix}dogs`" : 'dogs';
    $availTable = $prefix ? "`{$prefix}availability`" : 'availability';

    $db->beginTransaction();

    try {
        // Insert users
        $nowExpr = ($mode === 'mysql') ? 'NOW()' : "datetime('now')";
        $stmtUser = $db->prepare("INSERT INTO $usersTable (name, age, city, bio, email, password, is_available_today, latitude, longitude, last_location_update, rating, rating_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, $nowExpr, ?, ?)");
        foreach ($users as $i => $u) {
            $city = $u[2];
            $lat = isset($cityCoords[$city]) ? $cityCoords[$city][0] + (rand(-100,100) / 10000) : null;
            $lng = isset($cityCoords[$city]) ? $cityCoords[$city][1] + (rand(-100,100) / 10000) : null;
            $stmtUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $password, $u[5], $lat, $lng, $ratings[$i][0], $ratings[$i][1]]);
        }

        // Insert dogs
        $stmtDog = $db->prepare("INSERT INTO $dogsTable (user_id, name, breed, size, personality, walk_distance) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($dogs as $d) {
            $stmtDog->execute($d);
        }

        // Insert availability
        $stmtAvail = $db->prepare("INSERT INTO $availTable (user_id, time_slot) VALUES (?, ?)");
        foreach ($availability as $a) {
            foreach ($a[1] as $slot) {
                $stmtAvail->execute([$a[0], $slot]);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// Run initialization
initDatabase();
