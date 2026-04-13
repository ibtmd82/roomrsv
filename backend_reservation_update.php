<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$stmt = $db->prepare("UPDATE reservations SET name = :name, start = :start, end = :end, room_id = :room, status = :status, paid = :paid WHERE id = :id");

$stmt->bindParam(':id', $params->id);
$stmt->bindParam(':start', $params->start);
$stmt->bindParam(':end', $params->end);
$stmt->bindParam(':name', $params->text);
$stmt->bindParam(':room', $params->resource);
$stmt->bindParam(':status', $params->status);
$stmt->bindParam(':paid', $params->paid);
$stmt->execute();

class Result {}

$response = new Result();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);
