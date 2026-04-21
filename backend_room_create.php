<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$name = $params->name;
$capacity = $params->capacity;
$price = isset($params->price) ? floatval($params->price) : 0;

$stmt = $db->prepare("INSERT INTO rooms (name, capacity, status, price) VALUES (:name, :capacity, 'Ready', :price)");
$stmt->bindValue(':name', $name);
$stmt->bindValue(':capacity', $capacity);
$stmt->bindValue(':price', $price);
$stmt->execute();

$newId = $db->lastInsertId();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->status = "Ready";
$response->price = $price;

header('Content-Type: application/json');
echo json_encode($response);
