<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$newStart = isset($params->newStart) ? $params->newStart : null;
$newEnd = isset($params->newEnd) ? $params->newEnd : null;
$id = isset($params->id) ? intval($params->id) : 0;
$newResource = isset($params->newResource) ? intval($params->newResource) : 0;

$stmt = $db->prepare("SELECT id FROM trip_reservations WHERE tenant_id = :tenant_id AND id <> :id AND vehicle_id = :resource AND NOT ((`end` <= :start) OR (start >= :end)) LIMIT 1");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':start', $newStart);
$stmt->bindValue(':end', $newEnd);
$stmt->bindValue(':id', $id);
$stmt->bindValue(':resource', $newResource);
$stmt->execute();

if ($stmt->fetchColumn()) {
    $response = new stdClass();
    $response->result = 'Error';
    $response->message = 'Xe đã có chuyến trong khoảng thời gian này.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$updateStmt = $db->prepare("UPDATE trip_reservations SET start = :start, `end` = :end, vehicle_id = :resource WHERE id = :id AND tenant_id = :tenant_id");
$updateStmt->bindValue(':tenant_id', $tenantId);
$updateStmt->bindValue(':start', $newStart);
$updateStmt->bindValue(':end', $newEnd);
$updateStmt->bindValue(':id', $id);
$updateStmt->bindValue(':resource', $newResource);
$updateStmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);

