<?php

/**
 * Database layer — returns a single shared PDO connection (singleton pattern).
 *
 * We use SQLite so the whole database is one file on disk (db/tasks.db), which
 * makes the project completely self-contained — no MySQL/Postgres server needed.
 * PDO (PHP Data Objects) is used so every query uses prepared statements, which
 * prevents SQL injection attacks.
 *
 * The schema is created here on first run, so there is no separate migration step.
 */
function getDB(): PDO {

    // Static variable persists across calls within the same PHP request,
    // so we only open/configure the connection once per request (singleton).
    static $db = null;

    if ($db !== null) return $db;

    $dbDir = __DIR__ . '/../db';

    if (!is_dir($dbDir)) mkdir($dbDir, 0755, true);

    // Connect to the SQLite file; PDO will create it if it does not exist.
    $db = new PDO('sqlite:' . $dbDir . '/tasks.db');

    // Throw exceptions on errors instead of silently returning false.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Return rows as associative arrays (e.g. $row['title']) by default.
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SQLite disables foreign keys by default; this enables ON DELETE CASCADE.
    $db->exec('PRAGMA foreign_keys = ON');

    // ── Schema ───────────────────────────────────────────────────────────────
    // CREATE TABLE IF NOT EXISTS means these are safe to run on every boot;
    // they only execute DDL when the table does not already exist.

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,   -- bcrypt hash, never the raw password
        avatar TEXT DEFAULT NULL,      -- filename inside uploads/avatars/
        bio TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        due_date DATE,
        due_time TEXT DEFAULT '',
        status TEXT DEFAULT 'pending',   -- 'pending' | 'completed'
        priority TEXT DEFAULT 'medium',  -- 'low' | 'medium' | 'high'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS task_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        original_name TEXT NOT NULL,  -- the filename the user sees
        stored_name TEXT NOT NULL,    -- the randomised name on disk (prevents guessing)
        file_size INTEGER NOT NULL,
        mime_type TEXT NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // ── Lightweight migrations ────────────────────────────────────────────────
    // ALTER TABLE fails if the column already exists, so we silence the error.
    // This lets us add new columns without a dedicated migration runner.
    try { $db->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT ''"); } catch(Exception $e) {}

    return $db;

}
