<?php

require_once 'includes/auth_check.php';

require_once 'includes/db.php';

$today    = date('Y-m-d');

$greeting = (int)date('H') < 12 ? 'Good morning' : ((int)date('H') < 18 ? 'Good afternoon' : 'Good evening');

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard – TaskFlow</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body class="app-body">

<?php include 'includes/nav.php'; ?>

<main class="main-content">

    <!-- Page Header -->

    <header class="page-header">

        <div>

            <h2 class="page-greeting"><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>

            <p class="page-date"><?= date('l, F j, Y') ?></p>

        </div>

        <button class="btn btn-primary" id="openCreateModal">

            <span>+</span> New Task

        </button>

    </header>

    <!-- Stats Row -->

    <div class="stats-grid" id="statsGrid">

        <div class="stat-card">

            <div class="stat-icon stat-icon-blue">⊞</div>

            <div class="stat-info">

                <div class="stat-value" id="statTotal">—</div>

                <div class="stat-label">Total Today</div>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon stat-icon-green">✓</div>

            <div class="stat-info">

                <div class="stat-value" id="statCompleted">—</div>

                <div class="stat-label">Completed</div>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon stat-icon-yellow">◷</div>

            <div class="stat-info">

                <div class="stat-value" id="statPending">—</div>

                <div class="stat-label">Pending</div>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon stat-icon-purple">★</div>

            <div class="stat-info">

                <div class="stat-value" id="statHigh">—</div>

                <div class="stat-label">High Priority</div>

            </div>

        </div>

    </div>

    <!-- Task Section -->

    <section class="tasks-section">

        <div class="tasks-header">

            <h3 class="tasks-title">Today's Tasks</h3>

            <div class="filter-tabs">

                <button class="filter-tab active" data-filter="all">All</button>

                <button class="filter-tab" data-filter="pending">Pending</button>

                <button class="filter-tab" data-filter="completed">Completed</button>

            </div>

        </div>

        <div id="taskList" class="task-list">

            <div class="loading-spinner">Loading tasks…</div>

        </div>

    </section>

</main>

<!-- Task Modal -->

<div class="modal-overlay" id="modalOverlay">

    <div class="modal" id="taskModal">

        <div class="modal-header">

            <h3 class="modal-title" id="modalTitle">New Task</h3>

            <button class="modal-close" id="modalClose">✕</button>

        </div>

        <div class="modal-body">

            <input type="hidden" id="taskId">

            <div class="form-group">

                <label class="form-label">Title <span class="required">*</span></label>

                <input type="text" id="taskTitle" class="form-input" placeholder="What needs to be done?">

            </div>

            <div class="form-group">

                <label class="form-label">Description</label>

                <textarea id="taskDescription" class="form-input form-textarea" placeholder="Add more details…"></textarea>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label class="form-label">Due Date</label>

                    <input type="date" id="taskDueDate" class="form-input">

                </div>

                <div class="form-group">

                    <label class="form-label">Due Time</label>

                    <input type="time" id="taskDueTime" class="form-input">

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label class="form-label">Priority</label>

                    <select id="taskPriority" class="form-input form-select">

                        <option value="low">Low</option>

                        <option value="medium" selected>Medium</option>

                        <option value="high">High</option>

                    </select>

                </div>

                <div class="form-group">

                    <label class="form-label">Status</label>

                    <select id="taskStatus" class="form-input form-select">

                        <option value="pending">Pending</option>

                        <option value="completed">Completed</option>

                    </select>

                </div>

            </div>

            <div class="form-group">

                <label class="form-label">Notes</label>

                <textarea id="taskNotes" class="form-input form-textarea" placeholder="Any extra notes…"></textarea>

            </div>

        </div>

        <div class="modal-footer">

            <button class="btn btn-ghost" id="modalCancel">Cancel</button>

            <button class="btn btn-primary" id="saveTask">Save Task</button>

        </div>

    </div>

</div>

<!-- Delete Confirm -->

<div class="modal-overlay" id="deleteOverlay">

    <div class="modal modal-sm">

        <div class="modal-header">

            <h3 class="modal-title">Delete Task</h3>

            <button class="modal-close" id="deleteClose">✕</button>

        </div>

        <div class="modal-body">

            <p>Are you sure you want to delete this task? This action cannot be undone.</p>

        </div>

        <div class="modal-footer">

            <button class="btn btn-ghost" id="deleteCancelBtn">Cancel</button>

            <button class="btn btn-danger" id="confirmDelete">Delete</button>

        </div>

    </div>

</div>

<script>

    const TODAY = '<?= $today ?>';

</script>

<script src="js/dashboard.js"></script>

</body>

</html>
