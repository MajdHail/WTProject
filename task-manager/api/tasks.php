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

$body = [];

if ($method === 'PUT' || $method === 'DELETE') {

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

}

try {

    switch ($method) {

        case 'GET':

            handleGet($db, $userId);

            break;

        case 'POST':

            handlePost($db, $userId);

            break;

        case 'PUT':

            handlePut($db, $userId, $body);

            break;

        case 'DELETE':

            handleDelete($db, $userId, $body);

            break;

        default:

            http_response_code(405);

            echo json_encode(['error' => 'Method not allowed']);

    }

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

function handleGet(PDO $db, int $userId): void {

    $params = ['user_id' => $userId];

    $where  = 'user_id = :user_id';

    if (!empty($_GET['date'])) {

        $where          .= ' AND due_date = :date';

        $params['date']  = $_GET['date'];

    } elseif (!empty($_GET['month'])) {

        $where           .= " AND strftime('%Y-%m', due_date) = :month";

        $params['month']  = $_GET['month'];

    }
    

    $stmt = $db->prepare("SELECT * FROM tasks WHERE $where ORDER BY due_date ASC, due_time ASC, created_at DESC");

    $stmt->execute($params);

    echo json_encode($stmt->fetchAll());

}

function handlePost(PDO $db, int $userId): void {

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $title = trim($data['title'] ?? '');

    if (!$title) {

        http_response_code(422);

        echo json_encode(['error' => 'Title is required']);

        return;

    }

    $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, notes, due_date, due_time, status, priority)
                          VALUES (:user_id, :title, :desc, :notes, :due_date, :due_time, :status, :priority)");

    $stmt->execute([

        'user_id'  => $userId,

        'title'    => $title,

        'desc'     => trim($data['description'] ?? ''),

        'notes'    => trim($data['notes'] ?? ''),

        'due_date' => $data['due_date'] ?: null,

        'due_time' => trim($data['due_time'] ?? ''),

        'status'   => in_array($data['status'] ?? '', ['pending', 'completed']) ? $data['status'] : 'pending',

        'priority' => in_array($data['priority'] ?? '', ['low', 'medium', 'high']) ? $data['priority'] : 'medium',

    ]);

    $id   = $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');

    $stmt->execute([$id]);

    http_response_code(201);

    echo json_encode($stmt->fetch());

}

function handlePut(PDO $db, int $userId, array $data): void {

    $id = (int)($data['id'] ?? 0);

    if (!$id) {

        http_response_code(422);

        echo json_encode(['error' => 'Task ID required']);

        return;

    }

    $stmt = $db->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');

    $stmt->execute([$id, $userId]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode(['error' => 'Task not found']);

        return;

    }

    $fields = [];

    $params = ['id' => $id, 'user_id' => $userId];

    foreach (['title', 'description', 'notes', 'due_date', 'due_time', 'status', 'priority'] as $f) {

        if (array_key_exists($f, $data)) {

            $fields[]   = "$f = :$f";

            $params[$f] = $data[$f];

        }

    }

    if (empty($fields)) {

        http_response_code(422);

        echo json_encode(['error' => 'No fields to update']);

        return;

    }

    $fields[] = "updated_at = CURRENT_TIMESTAMP";

    $db->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id AND user_id = :user_id')

       ->execute($params);

    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');

    $stmt->execute([$id]);

    echo json_encode($stmt->fetch());

}

function handleDelete(PDO $db, int $userId, array $body): void {

    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {

        http_response_code(422);

        echo json_encode(['error' => 'Task ID required']);

        return;

    }

    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');

    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {

        http_response_code(404);

        echo json_encode(['error' => 'Task not found']);

        return;

    }

    echo json_encode(['success' => true]);

}
