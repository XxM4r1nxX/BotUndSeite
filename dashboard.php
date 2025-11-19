<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
ensure_system_bootstrap($pdo);
$user = require_login($pdo);
$permissions = user_permissions($pdo, (int)$user['id']);
$maintenanceActive = is_maintenance_mode($pdo);

if ($maintenanceActive && !user_has_permission($pdo, (int)$user['id'], 'toggle_maintenance')) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard | Wartungsmodus</title>
        <link rel="stylesheet" href="assets/styles.css">
    </head>
    <body>
        <div class="container">
            <div class="navbar">
                <div class="brand"><span style="font-size:22px;">🤖</span><span>Bot Dashboard</span></div>
                <div class="nav-actions"><a class="button secondary" href="logout.php">Logout</a></div>
            </div>
            <div class="card" style="max-width:720px; margin:40px auto; text-align:center;">
                <div class="section-title"><h2>Wartungsmodus aktiv</h2><span class="badge">Read-only</span></div>
                <p>Das Dashboard befindet sich im Wartungsmodus. Bitte später erneut versuchen.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
require_once __DIR__ . '/functions.php';
$user = require_login($pdo);
$permissions = user_permissions($pdo, (int)$user['id']);

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add user
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        if (!user_has_permission($pdo, (int)$user['id'], 'manage_users')) {
            $error = 'Du hast keine Berechtigung Benutzer anzulegen.';
        } else {
            $newUser = trim($_POST['new_username'] ?? '');
            $newPass = trim($_POST['new_password'] ?? '');
            $newPermissions = $_POST['new_permissions'] ?? [];
            if ($newUser && $newPass) {
                try {
                    create_user($pdo, $newUser, $newPass, $newPermissions);
                    $notice = 'Neuer Benutzer wurde erstellt und Berechtigungen gesetzt.';
                } catch (PDOException $e) {
                    $error = 'Fehler beim Anlegen: ' . htmlspecialchars($e->getMessage());
                }
            } else {
                $error = 'Bitte Benutzername und Passwort ausfüllen.';
            }
        }
    }

    // Update permissions
    if (isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
        if (!user_has_permission($pdo, (int)$user['id'], 'manage_users')) {
            $error = 'Du hast keine Berechtigung, Rechte zu bearbeiten.';
        } else {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $perms = $_POST['permissions'] ?? [];
            sync_user_permissions($pdo, $targetUserId, $perms);
            $notice = 'Berechtigungen aktualisiert.';
        }
    }

    // Delete transcript
    if (isset($_POST['action']) && $_POST['action'] === 'delete_transcript') {
        if (!user_has_permission($pdo, (int)$user['id'], 'delete_transcripts')) {
            $error = 'Du darfst keine Transkripte löschen.';
        } else {
            delete_transcript($pdo, (int)($_POST['transcript_id'] ?? 0));
            $notice = 'Transkript entfernt.';
        }
    }

    // Unlock API tab
    if (isset($_POST['action']) && $_POST['action'] === 'unlock_api') {
        if (!user_has_permission($pdo, (int)$user['id'], 'api_docs')) {
            $error = 'Dir fehlt die API-Berechtigung.';
        } else {
            $apiPassword = trim($_POST['api_password'] ?? '');
            if ($apiPassword === 'BotAPI') {
                $_SESSION['api_unlocked'] = true;
                $notice = 'API-Reiter freigeschaltet.';
            } else {
                $error = 'Falsches API-Passwort.';
            }
        }
    }

    // Toggle maintenance mode
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_maintenance') {
        if (!user_has_permission($pdo, (int)$user['id'], 'toggle_maintenance')) {
            $error = 'Dir fehlt die Berechtigung für den Wartungsmodus.';
        } else {
            $maintenanceActive = !is_maintenance_mode($pdo);
            set_setting($pdo, 'maintenance_mode', $maintenanceActive ? 'on' : 'off');
            $notice = $maintenanceActive ? 'Wartungsmodus aktiviert.' : 'Wartungsmodus deaktiviert.';
        }
    }
}

$allPermissions = list_permissions($pdo);
$users = list_users($pdo);
$transcripts = list_transcripts($pdo);
$apiUnlocked = $_SESSION['api_unlocked'] ?? false;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Bot Portal | Dashboard</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="brand">
                <span style="font-size:22px;">🤖</span>
                <span>Bot Dashboard</span>
            </div>
            <div class="nav-actions">
                <span class="badge">Eingeloggt als <?= htmlspecialchars($user['username']) ?></span>
                <a class="button secondary" href="logout.php">Logout</a>
            </div>
        </div>

        <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card" style="margin-bottom:18px;">
            <div class="section-title">
                <h2>Wartungsmodus</h2>
                <span class="badge">Systemstatus</span>
            </div>
            <div class="split" style="align-items:center;">
                <div>
                    <p style="margin:0;">Aktueller Status: <strong><?= $maintenanceActive ? 'Aktiv' : 'Deaktiviert' ?></strong></p>
                    <p style="margin:4px 0 0; color:var(--muted);">Im Wartungsmodus sind nur berechtigte Admins zugelassen.</p>
                </div>
                <?php if (user_has_permission($pdo, (int)$user['id'], 'toggle_maintenance')): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_maintenance">
                        <button class="button" type="submit" style="background: <?= $maintenanceActive ? 'var(--accent2)' : 'var(--accent)' ?>;">
                            <?= $maintenanceActive ? 'Wartung beenden' : 'Wartung aktivieren' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge">Keine Berechtigung zum Umschalten</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <div class="section-title">
                    <h2>Modul: Ticket-Transcripte</h2>
                    <span class="badge">API ready</span>
                </div>
                <p>Alle hochgeladenen Ticket-HTMLs werden hier angezeigt. Downloads & Löschungen folgen deinen Berechtigungen.</p>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr><th>Titel</th><th>Hochgeladen</th><th>Aktionen</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transcripts as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['title']) ?></td>
                                <td><?= htmlspecialchars($t['uploaded_at']) ?></td>
                                <td class="action-row">
                                    <?php if (user_has_permission($pdo, (int)$user['id'], 'view_transcripts')): ?>
                                        <a class="button secondary" href="<?= htmlspecialchars($t['file_path']) ?>" target="_blank">Ansehen</a>
                                    <?php endif; ?>
                                    <?php if (user_has_permission($pdo, (int)$user['id'], 'download_transcripts')): ?>
                                        <a class="button" href="download.php?id=<?= (int)$t['id'] ?>">Download</a>
                                    <?php endif; ?>
                                    <?php if (user_has_permission($pdo, (int)$user['id'], 'delete_transcripts')): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_transcript">
                                            <input type="hidden" name="transcript_id" value="<?= (int)$t['id'] ?>">
                                            <button class="button danger" type="submit">Löschen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transcripts)): ?>
                            <tr><td colspan="3" style="color:var(--muted);">Noch keine Transkripte eingetroffen.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="section-title">
                    <h2>API-Reiter</h2>
                    <span class="badge">Extra gesichert</span>
                </div>
                <p>Nur wer die Spezialberechtigung besitzt und das API-Passwort kennt, kann die Schnittstellen nutzen.</p>
                <?php if (!$apiUnlocked): ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="action" value="unlock_api">
                        <div style="grid-column: span 2;">
                            <label>API-Passwort</label>
                            <input type="password" name="api_password" placeholder="BotAPI" required>
                        </div>
                        <div style="grid-column: span 2; display:flex; justify-content:flex-end;">
                            <button class="button" type="submit">Reiter entsperren</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="notice">API-Reiter ist entsperrt.</div>
                    <h3>Transkript Upload Endpoint</h3>
                    <p>Erlaubt dem Bot HTML-Transkripte hochzuladen.</p>
                    <div class="code-block">
POST /api/transcript_upload.php
Headers: none (Passwort im Body)
Body (multipart/form-data):
- api_password = BotAPI
- title = Titel des Transkripts
- file = HTML-Datei
                    </div>
                    <p>Antwort: JSON mit Erfolgsstatus & Download-URL. Jede weitere Modul-API kann ähnlich ergänzt werden.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="split" style="margin-top:18px;">
            <div class="card">
                <div class="section-title">
                    <h2>Benutzer & Berechtigungen</h2>
                    <span class="badge">Rollenbasiert</span>
                </div>
                <?php if (user_has_permission($pdo, (int)$user['id'], 'manage_users')): ?>
                    <h3>Neuen Benutzer anlegen</h3>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="action" value="add_user">
                        <div>
                            <label>Benutzername</label>
                            <input type="text" name="new_username" required>
                        </div>
                        <div>
                            <label>Passwort</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Berechtigungen auswählen</label>
                            <div class="chip-list">
                                <?php foreach ($allPermissions as $perm): ?>
                                    <label class="chip">
                                        <input type="checkbox" name="new_permissions[]" value="<?= htmlspecialchars($perm['code']) ?>">
                                        <?= htmlspecialchars($perm['label']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="grid-column: span 2; display:flex; justify-content:flex-end;">
                            <button class="button" type="submit">Anlegen</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>Keine Berechtigung zum Anlegen neuer Nutzer.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title">
                    <h2>Bestehende Nutzer</h2>
                    <span class="badge">Live-Update</span>
                </div>
                <?php foreach ($users as $u): ?>
                    <div style="border-bottom:1px solid var(--border); padding:10px 0;">
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                        <div class="chip-list">
                            <?php foreach (user_permissions($pdo, (int)$u['id']) as $code): ?>
                                <span class="chip"><?= htmlspecialchars($code) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (user_has_permission($pdo, (int)$user['id'], 'manage_users')): ?>
                            <form method="POST" class="form-grid" style="margin-top:10px;">
                                <input type="hidden" name="action" value="update_permissions">
                                <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                <div style="grid-column: span 2;">
                                    <label>Berechtigungen anpassen</label>
                                    <div class="chip-list">
                                        <?php foreach ($allPermissions as $perm): ?>
                                            <label class="chip">
                                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm['code']) ?>" <?php if (in_array($perm['code'], user_permissions($pdo, (int)$u['id']))) echo 'checked'; ?>>
                                                <?= htmlspecialchars($perm['label']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div style="grid-column: span 2; display:flex; justify-content:flex-end;">
                                    <button class="button secondary" type="submit">Speichern</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
