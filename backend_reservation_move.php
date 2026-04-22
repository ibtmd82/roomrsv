<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$newStart = $params->newStart;
$newEnd = $params->newEnd;
$id = $params->id;
$newResource = $params->newResource;

$stmt = $db->prepare("SELECT * FROM reservations WHERE tenant_id = :tenant_id AND NOT ((`end` <= :start) OR (start >= :end)) AND id <> :id AND room_id = :resource");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':start', $newStart);
$stmt->bindValue(':end', $newEnd);
$stmt->bindValue(':id', $id);
$stmt->bindValue(':resource', $newResource);
$stmt->execute();
$overlaps = $stmt->rowCount() > 0;

if ($overlaps) {
    $response = new stdClass();
    $response->result = 'Error';
    $response->message = 'This reservation overlaps with an existing reservation.';

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("UPDATE reservations SET start = :start, `end` = :end, room_id = :resource WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':start', $newStart);
$stmt->bindValue(':end', $newEnd);
$stmt->bindValue(':id', $id);
$stmt->bindValue(':resource', $newResource);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);
