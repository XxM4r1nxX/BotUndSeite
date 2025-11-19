<?php
require_once __DIR__ . '/config.php';
session_start();

function current_user(PDO $pdo): ?array
{
    if (isset($_SESSION['user_id'])) {
        return fetch_user_by_id($pdo, (int)$_SESSION['user_id']);
    }

    // Fallback to remember-me cookie
    if (!empty($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = :token AND remember_expires > NOW()");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return $user;
        }
    }

    return null;
}

function fetch_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function is_maintenance_mode(PDO $pdo): bool
{
    return get_setting($pdo, 'maintenance_mode', 'off') === 'on';
}

function login(PDO $pdo, string $username, string $password, bool $remember): bool
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
            $update = $pdo->prepare("UPDATE users SET remember_token = :token, remember_expires = :expires WHERE id = :id");
            $update->execute([
                ':token' => $token,
                ':expires' => $expires,
                ':id' => $user['id']
            ]);
            setcookie('remember_me', $token, time() + 86400, '/', '', false, true);
        }

        return true;
    }

    return false;
}

function logout(PDO $pdo): void
{
    if (isset($_SESSION['user_id'])) {
        $update = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = :id");
        $update->execute([':id' => $_SESSION['user_id']]);
    }
    $_SESSION = [];
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/');
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function user_permissions(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT p.code FROM permissions p JOIN user_permissions up ON p.id = up.permission_id WHERE up.user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function user_has_permission(PDO $pdo, int $userId, string $permission): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM permissions p JOIN user_permissions up ON p.id = up.permission_id WHERE up.user_id = :user_id AND p.code = :code LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':code' => $permission]);
    return (bool)$stmt->fetchColumn();
}

function create_user(PDO $pdo, string $username, string $password, array $permissions): int
{
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :hash)");
    $stmt->execute([
        ':username' => $username,
        ':hash' => password_hash($password, PASSWORD_DEFAULT)
    ]);
    $userId = (int)$pdo->lastInsertId();
    sync_user_permissions($pdo, $userId, $permissions);
    return $userId;
}

function sync_user_permissions(PDO $pdo, int $userId, array $permissions): void
{
    $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :user_id")->execute([':user_id' => $userId]);
    $stmt = $pdo->prepare("SELECT id, code FROM permissions");
    $stmt->execute();
    $permissionMap = [];
    foreach ($stmt->fetchAll() as $perm) {
        $permissionMap[$perm['code']] = $perm['id'];
    }
    $insert = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (:user_id, :perm_id)");
    foreach ($permissions as $code) {
        if (isset($permissionMap[$code])) {
            $insert->execute([':user_id' => $userId, ':perm_id' => $permissionMap[$code]]);
        }
    }
}

function list_permissions(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM permissions ORDER BY code");
    return $stmt->fetchAll();
}

function list_users(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function list_transcripts(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM transcripts ORDER BY uploaded_at DESC");
    return $stmt->fetchAll();
}

function delete_transcript(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("SELECT file_path FROM transcripts WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $file = $stmt->fetchColumn();
    if ($file && file_exists($file)) {
        @unlink($file);
    }
    $pdo->prepare("DELETE FROM transcripts WHERE id = :id")->execute([':id' => $id]);
}
?>
