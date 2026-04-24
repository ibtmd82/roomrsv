<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$name = $params->name;
$capacity = $params->capacity;
$price = isset($params->price) ? floatval($params->price) : 0;
$priceDay = isset($params->priceDay) ? floatval($params->priceDay) : 300000;
$priceHour = isset($params->priceHour) ? floatval($params->priceHour) : 80000;

$stmt = $db->prepare("INSERT INTO rooms (tenant_id, name, capacity, status, price, price_day, price_hour) VALUES (:tenant_id, :name, :capacity, 'Ready', :price, :price_day, :price_hour)");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':capacity', $capacity);
$stmt->bindValue(':price', $price);
$stmt->bindValue(':price_day', $priceDay);
$stmt->bindValue(':price_hour', $priceHour);
$stmt->execute();

$newId = $db->lastInsertId();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->tenant_id = $tenantId;
$response->status = "Ready";
$response->price = $price;
$response->priceDay = $priceDay;
$response->priceHour = $priceHour;

header('Content-Type: application/json');
echo json_encode($response);
