<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$id = $params->id;
$name = $params->name;
$capacity = $params->capacity;
$status = $params->status;
$price = isset($params->price) ? floatval($params->price) : 0;

$stmt = $db->prepare("UPDATE rooms SET name = :name, capacity = :capacity, status = :status, price = :price WHERE id = :id AND tenant_id = :tenant_id");
$stmt->bindValue(':id', $id);
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':capacity', $capacity);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':price', $price);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Update successful';

header('Content-Type: application/json');
echo json_encode($response);
