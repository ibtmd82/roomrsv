<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = intval($tenantContext['tenant_id']);

$mode = isset($params->rentalMode) ? trim((string)$params->rentalMode) : 'both';
if (!in_array($mode, ['short_term', 'long_term', 'both'], true)) {
    $mode = 'both';
}
$shortTermDayThresholdHours = isset($params->shortTermDayThresholdHours) ? intval($params->shortTermDayThresholdHours) : 4;
if ($shortTermDayThresholdHours < 1) {
    $shortTermDayThresholdHours = 4;
}

$check = $db->prepare("SELECT id FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$check->bindValue(':tenant_id', $tenantId);
$check->execute();
$existingId = $check->fetchColumn();
$now = (new DateTime())->format('Y-m-d H:i:s');

if ($existingId) {
    $stmt = $db->prepare("UPDATE tenant_settings SET rental_mode = :rental_mode, short_term_day_threshold_hours = :short_term_day_threshold_hours, updated_at = :updated_at WHERE id = :id");
    $stmt->bindValue(':id', intval($existingId));
    $stmt->bindValue(':rental_mode', $mode);
    $stmt->bindValue(':short_term_day_threshold_hours', $shortTermDayThresholdHours);
    $stmt->bindValue(':updated_at', $now);
    $stmt->execute();
} else {
    $stmt = $db->prepare("INSERT INTO tenant_settings (tenant_id, rental_mode, short_term_day_threshold_hours, updated_at) VALUES (:tenant_id, :rental_mode, :short_term_day_threshold_hours, :updated_at)");
    $stmt->bindValue(':tenant_id', $tenantId);
    $stmt->bindValue(':rental_mode', $mode);
    $stmt->bindValue(':short_term_day_threshold_hours', $shortTermDayThresholdHours);
    $stmt->bindValue(':updated_at', $now);
    $stmt->execute();
}

$result = new stdClass();
$result->result = 'OK';
$result->tenantId = $tenantId;
$result->rentalMode = $mode;
$result->shortTermDayThresholdHours = $shortTermDayThresholdHours;
$result->updatedAt = $now;

header('Content-Type: application/json');
echo json_encode($result);
