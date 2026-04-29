<?php
require_once '_db.php';

$tenantContext = resolveTenantContext();
$tenantId = intval($tenantContext['tenant_id']);

$stmt = $db->prepare("SELECT * FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
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
$result->roomModuleEnabled = $row && isset($row['room_module_enabled']) ? intval($row['room_module_enabled']) === 1 : true;
$result->transportModuleEnabled = $row && isset($row['transport_module_enabled']) ? intval($row['transport_module_enabled']) === 1 : true;
$result->transportDashboardEnabled = $result->transportModuleEnabled;
$result->transportPricePerKm = $row && isset($row['transport_price_per_km']) ? floatval($row['transport_price_per_km']) : 6000;
if ($result->transportPricePerKm <= 0) {
    $result->transportPricePerKm = 6000;
}
$result->transportFuelPricePerLiter = $row && isset($row['transport_fuel_price_per_liter']) ? floatval($row['transport_fuel_price_per_liter']) : 22000;
if ($result->transportFuelPricePerLiter <= 0) {
    $result->transportFuelPricePerLiter = 22000;
}

header('Content-Type: application/json');
echo json_encode($result);
