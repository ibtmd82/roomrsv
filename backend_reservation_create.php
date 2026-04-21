<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$start = $params->start;
$end = $params->end;
$name = $params->text;
$room = $params->resource;
$discountType = isset($params->discountType) ? $params->discountType : 'fixed';
$discountValue = isset($params->discountValue) ? floatval($params->discountValue) : 0;
if ($discountValue < 0) {
    $discountValue = 0;
}

$priceStmt = $db->prepare("SELECT price FROM rooms WHERE id = :id");
$priceStmt->bindValue(':id', $room);
$priceStmt->execute();
$roomData = $priceStmt->fetch(PDO::FETCH_ASSOC);
$roomPrice = $roomData ? floatval($roomData['price']) : 0;

if ($discountType === 'percent') {
    if ($discountValue > 100) {
        $discountValue = 100;
    }
    $finalPrice = $roomPrice - ($roomPrice * $discountValue / 100);
} else {
    $discountType = 'fixed';
    $finalPrice = $roomPrice - $discountValue;
}

if ($finalPrice < 0) {
    $finalPrice = 0;
}

$stmt = $db->prepare("INSERT INTO reservations (name, start, `end`, room_id, status, paid, room_price, discount_type, discount_value, final_price) VALUES (:name, :start, :end, :room, 'New', 0, :room_price, :discount_type, :discount_value, :final_price)");
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':room', $room);
$stmt->bindValue(':room_price', $roomPrice);
$stmt->bindValue(':discount_type', $discountType);
$stmt->bindValue(':discount_value', $discountValue);
$stmt->bindValue(':final_price', $finalPrice);
$stmt->execute();

$newId = $db->lastInsertId();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->status = "New";
$response->paid = 0;
$response->roomPrice = $roomPrice;
$response->discountType = $discountType;
$response->discountValue = $discountValue;
$response->finalPrice = $finalPrice;

header('Content-Type: application/json');
echo json_encode($response);
