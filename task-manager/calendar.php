<?php

require_once 'includes/auth_check.php';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Calendar – TaskFlow</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body class="app-body">

<?php include 'includes/nav.php'; ?>

<main class="main-content">

    <header class="page-header">

        <div>

            <h2 class="page-greeting">Calendar</h2>

            <p class="page-date" id="calendarRangeLabel">—</p>

        </div>

        <div class="calendar-controls">

            <div class="view-toggle">

                <button class="view-btn active" id="btnWeekly" data-view="weekly">Weekly</button>

                <button class="view-btn" id="btnMonthly" data-view="monthly">Monthly</button>

            </div>

            <div class="nav-controls">

                <button class="nav-btn" id="prevBtn">&

                <button class="nav-btn today-btn" id="todayBtn">Today</button>

                <button class="nav-btn" id="nextBtn">&

            </div>

        </div>

    </header>

    <div class="calendar-container">

        <div id="calendarView"></div>

    </div>

</main>

<!-- Task Detail Panel -->

<div class="modal-overlay" id="dayModalOverlay">

    <div class="modal">

        <div class="modal-header">

            <h3 class="modal-title" id="dayModalTitle">Tasks</h3>

            <button class="modal-close" id="dayModalClose">✕</button>

        </div>

        <div class="modal-body" id="dayModalBody"></div>

    </div>

</div>

<script src="js/calendar.js"></script>

</body>

</html>
