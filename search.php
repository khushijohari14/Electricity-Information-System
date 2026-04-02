<?php
session_start();
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$q = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$results = [];

if (strlen($q) >= 1) {
    // Search consumers by consumer_id or name
    $r = $conn->query("SELECT consumer_id, name FROM consumers WHERE consumer_id LIKE '%$q%' OR name LIKE '%$q%' LIMIT 5");
    while ($row = $r->fetch_assoc()) {
        $results[] = ['type'=>'Consumer', 'label'=>$row['consumer_id'].' — '.$row['name'], 'page'=>'add-consumer', 'nav'=>1];
    }
    // Search meters by meter_number
    $r2 = $conn->query("SELECT m.meter_number, c.name, c.consumer_id FROM meters m JOIN consumers c ON m.consumer_id=c.id WHERE m.meter_number LIKE '%$q%' LIMIT 5");
    while ($row = $r2->fetch_assoc()) {
        $results[] = ['type'=>'Meter', 'label'=>$row['meter_number'].' — '.$row['consumer_id'].' ('.$row['name'].')', 'page'=>'assign-meter', 'nav'=>2];
    }
    // Search bills
    $r3 = $conn->query("SELECT b.bill_number, c.name FROM bills b JOIN consumers c ON b.consumer_id=c.id WHERE b.bill_number LIKE '%$q%' LIMIT 3");
    while ($row = $r3->fetch_assoc()) {
        $results[] = ['type'=>'Bill', 'label'=>$row['bill_number'].' — '.$row['name'], 'page'=>'generate-bill', 'nav'=>4];
    }
}
echo json_encode($results);
?>