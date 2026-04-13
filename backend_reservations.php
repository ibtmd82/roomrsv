<?php
require_once '_db.php';

$start = $_GET['start'];
$end = $_GET['end'];

$stmt = $db->prepare("SELECT * FROM reservations WHERE NOT ((end <= :start) OR (start >= :end))");
$stmt->bindParam(':start', $start);
$stmt->bindParam(':end', $end);
$stmt->execute();
$result = $stmt->fetchAll();

#[AllowDynamicProperties]
class Event {}
$events = array();

date_default_timezone_set("UTC");
$now = new DateTime("now");
$today = $now->setTime(0, 0, 0);

foreach($result as $row) {
    $e = new Event();
    $e->id = $row['id'];
    $e->text = $row['name'];
    $e->start = $row['start'];
    $e->end = $row['end'];
    $e->resource = $row['room_id'];
    $e->bubbleHtml = "Reservation details: <br/>".$e->text;
    
    // additional properties
    $e->status = $row['status'];
    $e->paid = intval($row['paid']);
    $events[] = $e;
}

header('Content-Type: application/json');
echo json_encode($events);
