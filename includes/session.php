<?php
function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
        header("Location: " . str_repeat("../", substr_count($_SERVER['PHP_SELF'], '/') - 2) . "login.php");
        exit();
    }
}

function requireConsumer() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'consumer') {
        header("Location: " . str_repeat("../", substr_count($_SERVER['PHP_SELF'], '/') - 2) . "login.php");
        exit();
    }
}
?>