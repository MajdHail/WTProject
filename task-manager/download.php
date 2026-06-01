<?php

session_start();

require_once 'includes/auth_check.php';

require_once 'includes/db.php';

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$db     = getDB();

$stmt = $db->prepare('SELECT * FROM task_attachments WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$att = $stmt->fetch();

if (!$att) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$filePath = __DIR__ . '/uploads/task-attachments/' . $att['stored_name'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File missing from server.';
    exit;
}

header('Content-Type: ' . $att['mime_type']);
header('Content-Disposition: attachment; filename="' . addslashes($att['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');

readfile($filePath);
exit;
