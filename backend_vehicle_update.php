<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = isset($params->id) ? intval($params->id) : 0;
$name = isset($params->name) ? trim((string)$params->name) : '';
$plateNo = isset($params->plateNo) ? trim((string)$params->plateNo) : '';
$capacity = isset($params->capacity) ? intval($params->capacity) : 4;
$status = isset($params->status) ? trim((string)$params->status) : 'Ready';
$fuelConsumptionPer100Km = isset($params->fuelConsumptionPer100Km) ? floatval($params->fuelConsumptionPer100Km) : 8;
$basePrice = isset($params->basePrice) ? floatval($params->basePrice) : 6000;

if ($capacity <= 0) {
    $capacity = 4;
}
if ($basePrice <= 0) {
    $basePrice = 6000;
}
if ($status === '') {
    $status = 'Ready';
}
if ($fuelConsumptionPer100Km <= 0) {
    $fuelConsumptionPer100Km = 8;
}

$stmt = $db->prepare("UPDATE vehicles SET name = :name, plate_no = :plate_no, capacity = :capacity, status = :status, fuel_consumption_per_100km = :fuel_consumption_per_100km, base_price = :base_price WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':plate_no', $plateNo);
$stmt->bindValue(':capacity', $capacity);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':fuel_consumption_per_100km', $fuelConsumptionPer100Km);
$stmt->bindValue(':base_price', $basePrice);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);

