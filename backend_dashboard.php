<?php
require_once '_db.php';

$result = new stdClass();

$roomsTotal = $db->query("SELECT COUNT(*) AS count FROM rooms")->fetch(PDO::FETCH_ASSOC);
$result->roomsTotal = intval($roomsTotal['count']);

$roomsByStatusStmt = $db->query("SELECT status, COUNT(*) AS count FROM rooms GROUP BY status");
$roomsByStatusRows = $roomsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$roomsByStatus = new stdClass();
foreach ($roomsByStatusRows as $row) {
    $roomsByStatus->{$row['status']} = intval($row['count']);
}
$result->roomsByStatus = $roomsByStatus;

$reservationsTotal = $db->query("SELECT COUNT(*) AS count FROM reservations")->fetch(PDO::FETCH_ASSOC);
$result->reservationsTotal = intval($reservationsTotal['count']);

$reservationsByStatusStmt = $db->query("SELECT status, COUNT(*) AS count FROM reservations GROUP BY status");
$reservationsByStatusRows = $reservationsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$reservationsByStatus = new stdClass();
foreach ($reservationsByStatusRows as $row) {
    $reservationsByStatus->{$row['status']} = intval($row['count']);
}
$result->reservationsByStatus = $reservationsByStatus;

$revenueStmt = $db->query("SELECT COALESCE(SUM(final_price), 0) AS total, COALESCE(AVG(final_price), 0) AS average FROM reservations");
$revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC);
$result->totalFinalPrice = floatval($revenue['total']);
$result->averageFinalPrice = floatval($revenue['average']);

$latestStmt = $db->query("SELECT id, name, start, `end`, status, final_price FROM reservations ORDER BY id DESC LIMIT 5");
$latestRows = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
$latest = [];
foreach ($latestRows as $row) {
    $item = new stdClass();
    $item->id = intval($row['id']);
    $item->name = $row['name'];
    $item->start = $row['start'];
    $item->end = $row['end'];
    $item->status = $row['status'];
    $item->finalPrice = floatval($row['final_price']);
    $latest[] = $item;
}
$result->latestReservations = $latest;

header('Content-Type: application/json');
echo json_encode($result);
