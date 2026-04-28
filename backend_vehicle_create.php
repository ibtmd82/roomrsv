<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

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

$stmt = $db->prepare("INSERT INTO vehicles (tenant_id, name, plate_no, capacity, status, fuel_consumption_per_100km, base_price) VALUES (:tenant_id, :name, :plate_no, :capacity, :status, :fuel_consumption_per_100km, :base_price)");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':plate_no', $plateNo);
$stmt->bindValue(':capacity', $capacity);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':fuel_consumption_per_100km', $fuelConsumptionPer100Km);
$stmt->bindValue(':base_price', $basePrice);
$stmt->execute();

$newId = $db->lastInsertId();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = intval($newId);
$response->tenant_id = intval($tenantId);
$response->status = $status;
$response->fuelConsumptionPer100Km = floatval($fuelConsumptionPer100Km);
$response->basePrice = floatval($basePrice);

header('Content-Type: application/json');
echo json_encode($response);

