<?php
require_once '_db.php';

$result = new stdClass();
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$roomsTotalStmt = $db->prepare("SELECT COUNT(*) AS count FROM rooms WHERE tenant_id = :tenant_id");
$roomsTotalStmt->bindValue(':tenant_id', $tenantId);
$roomsTotalStmt->execute();
$roomsTotal = $roomsTotalStmt->fetch(PDO::FETCH_ASSOC);
$result->roomsTotal = intval($roomsTotal['count']);

$roomsByStatusStmt = $db->prepare("SELECT status, COUNT(*) AS count FROM rooms WHERE tenant_id = :tenant_id GROUP BY status");
$roomsByStatusStmt->bindValue(':tenant_id', $tenantId);
$roomsByStatusStmt->execute();
$roomsByStatusRows = $roomsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$roomsByStatus = new stdClass();
foreach ($roomsByStatusRows as $row) {
    $roomsByStatus->{$row['status']} = intval($row['count']);
}
$result->roomsByStatus = $roomsByStatus;

$reservationsTotalStmt = $db->prepare("SELECT COUNT(*) AS count FROM reservations WHERE tenant_id = :tenant_id");
$reservationsTotalStmt->bindValue(':tenant_id', $tenantId);
$reservationsTotalStmt->execute();
$reservationsTotal = $reservationsTotalStmt->fetch(PDO::FETCH_ASSOC);
$result->reservationsTotal = intval($reservationsTotal['count']);

$reservationsByStatusStmt = $db->prepare("SELECT status, COUNT(*) AS count FROM reservations WHERE tenant_id = :tenant_id GROUP BY status");
$reservationsByStatusStmt->bindValue(':tenant_id', $tenantId);
$reservationsByStatusStmt->execute();
$reservationsByStatusRows = $reservationsByStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$reservationsByStatus = new stdClass();
foreach ($reservationsByStatusRows as $row) {
    $reservationsByStatus->{$row['status']} = intval($row['count']);
}
$result->reservationsByStatus = $reservationsByStatus;

$revenueStmt = $db->prepare("SELECT COALESCE(SUM(final_price), 0) AS total, COALESCE(AVG(final_price), 0) AS average FROM reservations WHERE tenant_id = :tenant_id");
$revenueStmt->bindValue(':tenant_id', $tenantId);
$revenueStmt->execute();
$revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC);
$result->totalFinalPrice = floatval($revenue['total']);
$result->averageFinalPrice = floatval($revenue['average']);

$latestStmt = $db->prepare("SELECT id, name, start, `end`, status, final_price FROM reservations WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 5");
$latestStmt->bindValue(':tenant_id', $tenantId);
$latestStmt->execute();
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
