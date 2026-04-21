<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);

$id = $params->id;
$stmt = $db->prepare("DELETE FROM reservations WHERE id = :id");
$stmt->bindValue(':id', $id);
$stmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Delete successful';

header('Content-Type: application/json');
echo json_encode($response);
