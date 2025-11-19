<?php
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST erforderlich']);
    exit;
}

$apiPassword = $_POST['api_password'] ?? '';
if ($apiPassword !== 'BotAPI') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Ungültiges API-Passwort']);
    exit;
}

// Optional: check for permission requirement via header for future modules
$title = trim($_POST['title'] ?? 'Ticket Transcript');
if (!$title) {
    $title = 'Ticket Transcript';
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datei-Upload fehlgeschlagen']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/uploads/transcripts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$originalName = basename($_FILES['file']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
if (strtolower($extension) !== 'html' && strtolower($extension) !== 'htm') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nur HTML-Dateien sind erlaubt']);
    exit;
}

$uniqueName = uniqid('transcript_', true) . '.html';
$targetPath = $uploadDir . $uniqueName;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Konnte Datei nicht speichern']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO transcripts (title, file_name, file_path) VALUES (:title, :file_name, :file_path)");
$stmt->execute([
    ':title' => $title,
    ':file_name' => $originalName,
    ':file_path' => 'uploads/transcripts/' . $uniqueName
]);

$response = [
    'success' => true,
    'message' => 'Transkript gespeichert',
    'download' => '../download.php?id=' . $pdo->lastInsertId(),
    'view' => '../uploads/transcripts/' . $uniqueName
];

echo json_encode($response);
