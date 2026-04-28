<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$newStart = isset($params->newStart) ? $params->newStart : null;
$newEnd = isset($params->newEnd) ? $params->newEnd : null;
$id = isset($params->id) ? intval($params->id) : 0;

$rowStmt = $db->prepare("SELECT vehicle_id FROM trip_reservations WHERE id = :id AND tenant_id = :tenant_id");
$rowStmt->bindValue(':id', $id);
$rowStmt->bindValue(':tenant_id', $tenantId);
$rowStmt->execute();
$vehicleId = intval($rowStmt->fetchColumn());

$overlapStmt = $db->prepare("SELECT id FROM trip_reservations WHERE tenant_id = :tenant_id AND vehicle_id = :vehicle_id AND id <> :id AND NOT ((`end` <= :start) OR (start >= :end)) LIMIT 1");
$overlapStmt->bindValue(':tenant_id', $tenantId);
$overlapStmt->bindValue(':vehicle_id', $vehicleId);
$overlapStmt->bindValue(':id', $id);
$overlapStmt->bindValue(':start', $newStart);
$overlapStmt->bindValue(':end', $newEnd);
$overlapStmt->execute();
if ($overlapStmt->fetchColumn()) {
    $response = new stdClass();
    $response->result = 'Error';
    $response->message = 'Xe đã có chuyến trong khoảng thời gian này.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("UPDATE trip_reservations SET start = :start, `end` = :end WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':start', $newStart);
$stmt->bindValue(':end', $newEnd);
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);

