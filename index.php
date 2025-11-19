<?php
require_once __DIR__ . '/functions.php';

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (login($pdo, $username, $password, $remember)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $message = 'Login fehlgeschlagen. Bitte prüfe Benutzername und Passwort.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Bot Portal | Login</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <div class="hero">
            <div class="glow">Willkommen im Bot-Dashboard</div>
            <h1>Modernes Control Center für deinen Discord Bot</h1>
            <p>Logge dich ein, um Tickets, APIs und Berechtigungen bequem zu steuern.</p>
        </div>
        <div class="card" style="max-width: 640px; margin: 0 auto;">
            <div class="section-title">
                <h2>Anmelden</h2>
                <span class="badge">Sicher & animiert</span>
            </div>
            <?php if ($message): ?>
                <div class="notice error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST" class="form-grid">
                <div style="grid-column: span 2;">
                    <label>Benutzername</label>
                    <input type="text" name="username" placeholder="z.B. M.Richter" required>
                </div>
                <div style="grid-column: span 2;">
                    <label>Passwort</label>
                    <input type="password" name="password" placeholder="Dein Passwort" required>
                </div>
                <div style="display:flex; align-items:center; gap:8px; grid-column: span 2;">
                    <input type="checkbox" id="remember" name="remember" style="width:auto;">
                    <label for="remember">24 Stunden angemeldet bleiben</label>
                </div>
                <div style="grid-column: span 2; display:flex; justify-content:flex-end;">
                    <button class="button" type="submit">Login</button>
                </div>
            </form>
            <p style="margin-top:16px; color: var(--muted);">Standard-Admin: <strong>M.Richter</strong> / <strong>TestBot</strong></p>
        </div>
        <footer>Entwickelt für modulare Erweiterbarkeit – Ticket-Transcripte, APIs und mehr.</footer>
    </div>
</body>
</html>
