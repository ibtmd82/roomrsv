<?php
require_once '_db.php';

$tenantContext = resolveTenantContext();
$tenantId = intval($tenantContext['tenant_id']);

$stmt = $db->prepare("SELECT rental_mode FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();
$mode = $stmt->fetchColumn();
if (!$mode) {
    $mode = 'both';
}
if (!in_array($mode, ['short_term', 'long_term', 'both'], true)) {
    $mode = 'both';
}

$result = new stdClass();
$result->result = 'OK';
$result->tenantId = $tenantId;
$result->rentalMode = $mode;

header('Content-Type: application/json');
echo json_encode($result);
