<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$newStart = $params->newStart;
$newEnd = $params->newEnd;
$id = $params->id;

$stmt = $db->prepare("UPDATE reservations SET start = :start, `end` = :end WHERE id = :id AND tenant_id = :tenant_id");
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
