<?php
session_start();

require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function require_role(string ...$roles): void
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        header('Location: dashboard.php');
        exit;
    }
}
