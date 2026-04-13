<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$stmt = $db->prepare("INSERT INTO reservations (name, start, end, room_id, status, paid) VALUES (:name, :start, :end, :room, 'New', 0)");
$stmt->bindParam(':start', $params->start);
$stmt->bindParam(':end', $params->end);
$stmt->bindParam(':name', $params->text);
$stmt->bindParam(':room', $params->resource);
$stmt->execute();

class Result {}

$response = new Result();
$response->result = 'OK';
$response->message = 'Created with id: '.$db->lastInsertId();

$response->id = $db->lastInsertId();
$response->status = "New";
$response->paid = 0;


header('Content-Type: application/json');
echo json_encode($response);
