<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = isset($params->id) ? intval($params->id) : 0;

$usageStmt = $db->prepare("SELECT id FROM trip_reservations WHERE tenant_id = :tenant_id AND vehicle_id = :vehicle_id LIMIT 1");
$usageStmt->bindValue(':tenant_id', $tenantId);
$usageStmt->bindValue(':vehicle_id', $id);
$usageStmt->execute();
if ($usageStmt->fetchColumn()) {
    http_response_code(409);
    $error = new stdClass();
    $error->result = 'Error';
    $error->message = 'Xe đã có chuyến, không thể xóa.';
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

$stmt = $db->prepare("DELETE FROM vehicles WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Delete successful';

header('Content-Type: application/json');
echo json_encode($response);

