<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'electricity_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:2rem;background:#1a0a0a;color:#ff6b6b;'>
        <h2>Database Connection Failed</h2>
        <p>" . $conn->connect_error . "</p>
        <p>Make sure XAMPP MySQL is running and import db/eis.sql in phpMyAdmin.</p>
    </div>");
}
$conn->set_charset("utf8mb4");

function logActivity($conn, $action, $type = 'system') {
    $action = $conn->real_escape_string($action);
    $type   = $conn->real_escape_string($type);
    $conn->query("INSERT INTO activity_log (action, type) VALUES ('$action', '$type')");
}
?>