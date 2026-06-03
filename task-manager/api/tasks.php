<?php

/**
 * REST API endpoint for tasks — /api/tasks.php
 *
 * Maps HTTP methods to CRUD operations:
 *   GET    → list tasks (filtered by ?date= or ?month=)
 *   POST   → create a new task   (JSON body)
 *   PUT    → update an existing task (JSON body with id)
 *   DELETE → delete a task       (JSON body with id)
 *
 * All responses are JSON. Every operation is scoped to the logged-in user,
 * so users can never read or modify another user's tasks.
 */

session_start();

require_once '../includes/db.php';

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────────────────
// Reject unauthenticated requests immediately with HTTP 401 Unauthorized.
if (empty($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['error' => 'Unauthorized']);

    exit;

}

// Cast to int so it can never be used as a string in a SQL injection attack.
$userId = (int)$_SESSION['user_id'];

$db     = getDB();

$method = $_SERVER['REQUEST_METHOD'];

// PUT and DELETE send their payload as a JSON body (not form data),
// because browsers send fetch() with Content-Type: application/json.
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

    // Catch any unhandled database or runtime error and return a 500.
    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

// ── Handlers ─────────────────────────────────────────────────────────────────

/**
 * GET /api/tasks.php
 * Optional query params:
 *   ?date=YYYY-MM-DD   → tasks for a specific day (used by the dashboard)
 *   ?month=YYYY-MM     → tasks for a whole month (used by the calendar)
 *   (no param)         → all tasks for the user
 */
function handleGet(PDO $db, int $userId): void {

    // Always start with the user_id filter so a user can only see their own tasks.
    $params = ['user_id' => $userId];

    $where  = 'user_id = :user_id';

    if (!empty($_GET['date'])) {

        // Named PDO parameter :date — the value is never interpolated directly.
        $where          .= ' AND due_date = :date';

        $params['date']  = $_GET['date'];

    } elseif (!empty($_GET['month'])) {

        // strftime extracts 'YYYY-MM' from the stored DATE column.
        $where           .= " AND strftime('%Y-%m', due_date) = :month";

        $params['month']  = $_GET['month'];

    }

    $stmt = $db->prepare("SELECT * FROM tasks WHERE $where ORDER BY due_date ASC, due_time ASC, created_at DESC");

    $stmt->execute($params);

    echo json_encode($stmt->fetchAll());

}

/**
 * POST /api/tasks.php
 * Creates a new task. Expects a JSON body with at least { title }.
 * Returns the newly-inserted task row as JSON with HTTP 201 Created.
 */
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

        // Coerce empty string to NULL so the DATE column stays clean.
        'due_date' => $data['due_date'] ?: null,

        'due_time' => trim($data['due_time'] ?? ''),

        // Whitelist validation — reject anything not in the allowed set.
        'status'   => in_array($data['status'] ?? '', ['pending', 'completed']) ? $data['status'] : 'pending',

        'priority' => in_array($data['priority'] ?? '', ['low', 'medium', 'high']) ? $data['priority'] : 'medium',

    ]);

    // Fetch the full row so the client gets all server-generated fields
    // (id, created_at, updated_at) in one round-trip.
    $id   = $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');

    $stmt->execute([$id]);

    http_response_code(201);

    echo json_encode($stmt->fetch());

}

/**
 * PUT /api/tasks.php
 * Partial update — only the fields present in the JSON body are changed.
 * The user_id check in the WHERE clause ensures users cannot edit others' tasks.
 */
function handlePut(PDO $db, int $userId, array $data): void {

    $id = (int)($data['id'] ?? 0);

    if (!$id) {

        http_response_code(422);

        echo json_encode(['error' => 'Task ID required']);

        return;

    }

    // Ownership check — 404 if the task belongs to a different user.
    $stmt = $db->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');

    $stmt->execute([$id, $userId]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode(['error' => 'Task not found']);

        return;

    }

    // Build the SET clause dynamically from only the keys the client sent.
    // array_key_exists (not isset) so that sending null explicitly clears a field.
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

    // Return the updated row so the client can refresh its local state.
    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');

    $stmt->execute([$id]);

    echo json_encode($stmt->fetch());

}

/**
 * DELETE /api/tasks.php
 * Deletes a task by id. The user_id in the WHERE clause is the ownership guard.
 * The ON DELETE CASCADE in the schema automatically removes all attachments too.
 */
function handleDelete(PDO $db, int $userId, array $body): void {

    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {

        http_response_code(422);

        echo json_encode(['error' => 'Task ID required']);

        return;

    }

    // Combining DELETE with the user_id check in one query is more efficient
    // than a separate SELECT-then-DELETE pair.
    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');

    $stmt->execute([$id, $userId]);

    // rowCount() returns how many rows were actually deleted.
    // 0 means either the task didn't exist or it belonged to another user.
    if ($stmt->rowCount() === 0) {

        http_response_code(404);

        echo json_encode(['error' => 'Task not found']);

        return;

    }

    echo json_encode(['success' => true]);

}
