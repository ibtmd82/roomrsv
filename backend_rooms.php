<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$capacity = isset($params->capacity) ? $params->capacity : '0';

$stmt = $db->prepare("SELECT * FROM rooms WHERE tenant_id = :tenant_id AND (capacity = :capacity OR :capacity = '0') ORDER BY name");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindParam(':capacity', $capacity); 
$stmt->execute();
$rooms = $stmt->fetchAll();

#[AllowDynamicProperties]
class Room {}

$result = array();

foreach($rooms as $room) {
  $r = new Room();
  $r->id = $room['id'];
  $r->tenant_id = intval($room['tenant_id']);
  $r->name = $room['name'];
  $r->capacity = intval($room['capacity']);
  $r->status = $room['status'];
  $r->price = floatval($room['price']);
  $result[] = $r;
}

header('Content-Type: application/json');
echo json_encode($result);
