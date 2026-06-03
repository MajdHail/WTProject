<?php

/**
 * Secure file download proxy — download.php?id=N
 *
 * Files are never served from their upload path directly; the browser downloads
 * them through this script, which enforces:
 *   - Authentication (auth_check.php redirects unauthenticated users)
 *   - Ownership: the attachment must belong to the logged-in user
 *   - Correct MIME type so the browser handles the file properly
 *   - RFC 5987 Content-Disposition so filenames with non-ASCII characters
 *     (accented letters, emoji, etc.) download with the correct name
 */

session_start();

require_once 'includes/auth_check.php';

require_once 'includes/db.php';

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$db     = getDB();

// Ownership check: if the row doesn't exist for this user, return 404.
// This also prevents users from downloading other users' attachments by ID.
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
// RFC 5987 encoding: strip control chars for the ASCII fallback, percent-encode for the UTF-8 parameter.
// This prevents HTTP response-splitting (via \r\n in a filename) and supports Unicode names.
$safeName = str_replace(["\r", "\n", '"', '\\'], '', $att['original_name']);
header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($att['original_name']));
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');

// Stream the file contents directly to the response body.
readfile($filePath);
exit;
