<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = isset($params->id) ? intval($params->id) : 0;
$status = isset($params->status) ? trim((string)$params->status) : '';
$allowed = ['Ready', 'Cleanup', 'Dirty'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    $response = new stdClass();
    $response->result = 'Error';
    $response->message = 'Invalid room status update payload.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$stmt = $db->prepare("UPDATE rooms SET status = :status WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':status', $status);
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Room status updated';
$response->status = $status;

header('Content-Type: application/json');
echo json_encode($response);
