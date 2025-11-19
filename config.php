<?php
declare(strict_types=1);

// Database configuration and bootstrap
$host = "localhost";
$dbname = "ticketsystem_webseite";
$user = "webseite_discord";
$pass = "~MiPmoss1on31w@Z";

$dsnNoDb = "mysql:host={$host};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Ensure database exists
    $pdoBootstrap = new PDO($dsnNoDb, $user, $pass, $options);
    $pdoBootstrap->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    die('Database bootstrap failed: ' . htmlspecialchars($e->getMessage()));
}

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Ensure tables exist
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    remember_token VARCHAR(255) NULL,
    remember_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS transcripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

const DEFAULT_ADMIN_USERNAME = 'M.Richter';
const DEFAULT_ADMIN_PASSWORD = 'TestBot';

function bootstrap_permissions(PDO $pdo): void
{
    $defaultPermissions = [
        'view_transcripts' => 'Transkripte ansehen',
        'download_transcripts' => 'Transkripte herunterladen',
        'delete_transcripts' => 'Transkripte löschen',
        'manage_users' => 'Benutzer & Rechte verwalten',
        'api_docs' => 'API-Bereich öffnen',
        'transcript_api_upload' => 'Transkript-API nutzen',
        'toggle_maintenance' => 'Wartungsmodus umschalten'
    ];

    foreach ($defaultPermissions as $code => $label) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (code, label) VALUES (:code, :label)");
        $stmt->execute([':code' => $code, ':label' => $label]);
    }
}

function bootstrap_admin(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = :username");
    $stmt->execute([':username' => DEFAULT_ADMIN_USERNAME]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :hash)");
        $stmt->execute([':username' => DEFAULT_ADMIN_USERNAME, ':hash' => $hash]);
        $adminId = (int)$pdo->lastInsertId();
    } else {
        $adminId = (int)$admin['id'];
        if (!password_verify(DEFAULT_ADMIN_PASSWORD, $admin['password_hash'])) {
            $reset = $pdo->prepare("UPDATE users SET password_hash = :hash, remember_token = NULL, remember_expires = NULL WHERE id = :id");
            $reset->execute([
                ':hash' => password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                ':id' => $adminId
            ]);
        }
    }

    $stmt = $pdo->query("SELECT id FROM permissions");
    $permIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($permIds as $permId) {
        $link = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (:user_id, :perm_id)");
        $link->execute([':user_id' => $adminId, ':perm_id' => $permId]);
    }
}

function bootstrap_settings(PDO $pdo): void
{
    $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('maintenance_mode', 'off')")->execute();
}

bootstrap_permissions($pdo);
bootstrap_admin($pdo);
bootstrap_settings($pdo);
