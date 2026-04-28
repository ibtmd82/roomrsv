<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = isset($params->id) ? intval($params->id) : 0;
$status = isset($params->status) ? trim((string)$params->status) : 'Ready';
if (!in_array($status, ['Ready', 'Busy', 'Maintenance'], true)) {
    $status = 'Ready';
}

$stmt = $db->prepare("UPDATE vehicles SET status = :status WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':status', $status);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);

