<?php

session_start();

require_once '../includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['error' => 'Unauthorized']);

    exit;

}

$userId = (int)$_SESSION['user_id'];

$db     = getDB();

$method = $_SERVER['REQUEST_METHOD'];

$uploadDir = __DIR__ . '/../uploads/task-attachments/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
];

$maxSize = 5 * 1024 * 1024; 

try {

    if ($method === 'POST') {

        
        $taskId = (int)($_POST['task_id'] ?? 0);

        if (!$taskId) {
            http_response_code(422);
            echo json_encode(['error' => 'task_id required']);
            exit;
        }

        
        $stmt = $db->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['error' => 'No file uploaded or upload error']);
            exit;
        }

        $file = $_FILES['file'];

        if ($file['size'] > $maxSize) {
            http_response_code(422);
            echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
            exit;
        }

        
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedMimes)) {
            http_response_code(422);
            echo json_encode(['error' => 'File type not allowed: ' . $mimeType]);
            exit;
        }

        $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
        $storedName = uniqid('att_', true) . ($ext ? '.' . $ext : '');
        $destPath   = $uploadDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO task_attachments (task_id, user_id, original_name, stored_name, file_size, mime_type)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$taskId, $userId, $file['name'], $storedName, $file['size'], $mimeType]);

        $id   = $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM task_attachments WHERE id = ?');
        $stmt->execute([$id]);

        http_response_code(201);
        echo json_encode($stmt->fetch());

    } elseif ($method === 'GET') {

        $taskId = (int)($_GET['task_id'] ?? 0);

        if (!$taskId) {
            http_response_code(422);
            echo json_encode(['error' => 'task_id required']);
            exit;
        }

        
        $stmt = $db->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        $stmt = $db->prepare('SELECT * FROM task_attachments WHERE task_id = ? ORDER BY uploaded_at DESC');
        $stmt->execute([$taskId]);

        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'DELETE') {

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? $_GET['id'] ?? 0);

        if (!$id) {
            http_response_code(422);
            echo json_encode(['error' => 'Attachment ID required']);
            exit;
        }

        $stmt = $db->prepare('SELECT * FROM task_attachments WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $att = $stmt->fetch();

        if (!$att) {
            http_response_code(404);
            echo json_encode(['error' => 'Attachment not found']);
            exit;
        }

        
        $filePath = $uploadDir . $att['stored_name'];
        if (file_exists($filePath)) unlink($filePath);

        $db->prepare('DELETE FROM task_attachments WHERE id = ?')->execute([$id]);

        echo json_encode(['success' => true]);

    } else {

        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);

    }

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);

}
