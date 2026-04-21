<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$id = $params->id;
$start = $params->start;
$end = $params->end;
$name = $params->text;
$room = $params->resource;
$status = $params->status;
$paid = $params->paid;
$discountType = isset($params->discountType) ? $params->discountType : 'fixed';
$discountValue = isset($params->discountValue) ? floatval($params->discountValue) : 0;
$roomPrice = isset($params->roomPrice) ? floatval($params->roomPrice) : null;
if ($discountValue < 0) {
    $discountValue = 0;
}

if ($roomPrice === null) {
    $priceStmt = $db->prepare("SELECT price FROM rooms WHERE id = :id");
    $priceStmt->bindValue(':id', $room);
    $priceStmt->execute();
    $roomData = $priceStmt->fetch(PDO::FETCH_ASSOC);
    $roomPrice = $roomData ? floatval($roomData['price']) : 0;
}

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

$stmt = $db->prepare("UPDATE reservations SET name = :name, start = :start, `end` = :end, room_id = :room, status = :status, paid = :paid, room_price = :room_price, discount_type = :discount_type, discount_value = :discount_value, final_price = :final_price WHERE id = :id");

$stmt->bindValue(':id', $id);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':room', $room);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':paid', $paid);
$stmt->bindValue(':room_price', $roomPrice);
$stmt->bindValue(':discount_type', $discountType);
$stmt->bindValue(':discount_value', $discountValue);
$stmt->bindValue(':final_price', $finalPrice);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);
