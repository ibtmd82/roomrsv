<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = isset($params->id) ? intval($params->id) : 0;

$feeStmt = $db->prepare("DELETE FROM trip_costs WHERE trip_reservation_id = :id AND tenant_id = :tenant_id");
$feeStmt->bindValue(':id', $id);
$feeStmt->bindValue(':tenant_id', $tenantId);
$feeStmt->execute();

$invoiceStmt = $db->prepare("DELETE FROM trip_invoices WHERE trip_reservation_id = :id AND tenant_id = :tenant_id");
$invoiceStmt->bindValue(':id', $id);
$invoiceStmt->bindValue(':tenant_id', $tenantId);
$invoiceStmt->execute();

$stmt = $db->prepare("DELETE FROM trip_reservations WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Delete successful';

header('Content-Type: application/json');
echo json_encode($response);

