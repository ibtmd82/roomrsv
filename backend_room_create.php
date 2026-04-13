<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$stmt = $db->prepare("INSERT INTO rooms (name, capacity, status) VALUES (:name, :capacity, 'Ready')");
$stmt->bindParam(':name', $params->name);
$stmt->bindParam(':capacity', $params->capacity);
$stmt->execute();

class Result {}

$response = new Result();
$response->result = 'OK';
$response->message = 'Created with id: '.$db->lastInsertId();
$response->id = $db->lastInsertId();
$response->status = "Ready";

header('Content-Type: application/json');
echo json_encode($response);
