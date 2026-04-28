<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$capacity = isset($params->capacity) ? $params->capacity : '0';

$stmt = $db->prepare("SELECT * FROM vehicles WHERE tenant_id = :tenant_id AND (capacity = :capacity OR :capacity = '0') ORDER BY name");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindParam(':capacity', $capacity);
$stmt->execute();
$rows = $stmt->fetchAll();

$result = [];
foreach($rows as $row) {
    $item = new stdClass();
    $item->id = intval($row['id']);
    $item->tenant_id = intval($row['tenant_id']);
    $item->name = $row['name'];
    $item->plateNo = isset($row['plate_no']) ? $row['plate_no'] : '';
    $item->capacity = intval($row['capacity']);
    $item->status = isset($row['status']) ? $row['status'] : 'Ready';
    $item->fuelConsumptionPer100Km = isset($row['fuel_consumption_per_100km']) ? floatval($row['fuel_consumption_per_100km']) : 8;
    $item->basePrice = isset($row['base_price']) ? floatval($row['base_price']) : 0;
    $result[] = $item;
}

header('Content-Type: application/json');
echo json_encode($result);

