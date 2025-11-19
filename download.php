<?php
require_once __DIR__ . '/functions.php';
$user = require_login($pdo);

if (!user_has_permission($pdo, (int)$user['id'], 'download_transcripts')) {
    http_response_code(403);
    exit('Keine Download-Berechtigung.');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM transcripts WHERE id = :id");
$stmt->execute([':id' => $id]);
$transcript = $stmt->fetch();

if (!$transcript || !file_exists($transcript['file_path'])) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

header('Content-Description: File Transfer');
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . basename($transcript['file_name']) . '"');
header('Content-Length: ' . filesize($transcript['file_path']));
readfile($transcript['file_path']);
exit;
