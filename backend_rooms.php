<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$capacity = isset($params->capacity) ? $params->capacity : '0';

$stmt = $db->prepare("SELECT * FROM rooms WHERE capacity = :capacity OR :capacity = '0' ORDER BY name");
$stmt->bindParam(':capacity', $capacity); 
$stmt->execute();
$rooms = $stmt->fetchAll();

#[AllowDynamicProperties]
class Room {}

$result = array();

foreach($rooms as $room) {
  $r = new Room();
  $r->id = $room['id'];
  $r->name = $room['name'];
  $r->capacity = intval($room['capacity']);
  $r->status = $room['status'];
  $result[] = $r;
}

header('Content-Type: application/json');
echo json_encode($result);
