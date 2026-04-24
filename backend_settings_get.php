<?php
require_once '_db.php';

$tenantContext = resolveTenantContext();
$tenantId = intval($tenantContext['tenant_id']);

$stmt = $db->prepare("SELECT rental_mode, short_term_day_threshold_hours FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$mode = $row && isset($row['rental_mode']) ? $row['rental_mode'] : null;
if (!$mode) {
    $mode = 'both';
}
if (!in_array($mode, ['short_term', 'long_term', 'both'], true)) {
    $mode = 'both';
}

$shortTermDayThresholdHours = $row && isset($row['short_term_day_threshold_hours']) ? intval($row['short_term_day_threshold_hours']) : 4;
if ($shortTermDayThresholdHours < 1) {
    $shortTermDayThresholdHours = 4;
}

$result = new stdClass();
$result->result = 'OK';
$result->tenantId = $tenantId;
$result->rentalMode = $mode;
$result->shortTermDayThresholdHours = $shortTermDayThresholdHours;

header('Content-Type: application/json');
echo json_encode($result);
