<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$start = isset($params->start) ? $params->start : null;
$end = isset($params->end) ? $params->end : null;
$name = isset($params->text) ? trim((string)$params->text) : '';
$vehicleId = isset($params->resource) ? intval($params->resource) : 0;
$driverName = isset($params->driverName) ? trim((string)$params->driverName) : '';
$distanceKm = isset($params->distanceKm) ? floatval($params->distanceKm) : 0;
$status = normalizeTripStatus(isset($params->status) ? $params->status : 'New');
$discountType = normalizeTripDiscountType(isset($params->discountType) ? $params->discountType : 'fixed');
$discountValue = sanitizeTripDiscountValue(isset($params->discountValue) ? $params->discountValue : 0, $discountType);
$paidAmount = isset($params->paidAmount) ? floatval($params->paidAmount) : 0;
$paymentStatus = normalizeTripPaymentStatus(isset($params->paymentStatus) ? $params->paymentStatus : 'unpaid');
$paymentMethod = isset($params->paymentMethod) ? trim((string)$params->paymentMethod) : null;
$paymentRef = isset($params->paymentRef) ? trim((string)$params->paymentRef) : null;
$note = isset($params->note) ? trim((string)$params->note) : '';
$tripCosts = extractTripCostsPayload($params);
$customerPayload = extractCustomerPayload($params);

if ($distanceKm < 0) {
    $distanceKm = 0;
}
if ($paidAmount < 0) {
    $paidAmount = 0;
}

$settingStmt = $db->prepare("SELECT transport_fuel_price_per_liter FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$settingStmt->bindValue(':tenant_id', $tenantId);
$settingStmt->execute();
$settingRow = $settingStmt->fetch(PDO::FETCH_ASSOC);
$fuelPricePerLiter = $settingRow && isset($settingRow['transport_fuel_price_per_liter']) ? floatval($settingRow['transport_fuel_price_per_liter']) : 22000;

$vehicleStmt = $db->prepare("SELECT base_price, fuel_consumption_per_100km FROM vehicles WHERE tenant_id = :tenant_id AND id = :vehicle_id LIMIT 1");
$vehicleStmt->bindValue(':tenant_id', $tenantId);
$vehicleStmt->bindValue(':vehicle_id', $vehicleId);
$vehicleStmt->execute();
$vehicleRow = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
$basePricePerKm = $vehicleRow && isset($vehicleRow['base_price']) ? floatval($vehicleRow['base_price']) : 0;
if ($basePricePerKm <= 0) {
    $basePricePerKm = 6000;
}
$fuelLitersPer100Km = $vehicleRow && isset($vehicleRow['fuel_consumption_per_100km']) ? floatval($vehicleRow['fuel_consumption_per_100km']) : 8;
$tripPrice = round(max(0, $distanceKm) * max(0, $basePricePerKm), 2);
$fuelEstimatedCost = round((max(0, $distanceKm) * max(0, $fuelLitersPer100Km) / 100) * max(0, $fuelPricePerLiter), 2);
$tripCostsTotal = calculateTripCostsTotal($tripCosts) + $fuelEstimatedCost;

$overlapStmt = $db->prepare("SELECT id FROM trip_reservations WHERE tenant_id = :tenant_id AND vehicle_id = :vehicle_id AND NOT ((`end` <= :start) OR (start >= :end)) LIMIT 1");
$overlapStmt->bindValue(':tenant_id', $tenantId);
$overlapStmt->bindValue(':vehicle_id', $vehicleId);
$overlapStmt->bindValue(':start', $start);
$overlapStmt->bindValue(':end', $end);
$overlapStmt->execute();
if ($overlapStmt->fetchColumn()) {
    http_response_code(409);
    $error = new stdClass();
    $error->result = 'Error';
    $error->message = 'Xe đã có chuyến trong khoảng thời gian này.';
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

$finalPrice = computeTripFinalTotal($tripPrice, $tripCostsTotal, $discountType, $discountValue);
$customerId = upsertReservationCustomer($db, $tenantId, $customerPayload);

$stmt = $db->prepare("INSERT INTO trip_reservations (tenant_id, customer_id, vehicle_id, name, driver_name, distance_km, fuel_estimated_cost, start, `end`, status, trip_price, discount_type, discount_value, final_price, paid_amount, payment_status, payment_method, payment_ref, note) VALUES (:tenant_id, :customer_id, :vehicle_id, :name, :driver_name, :distance_km, :fuel_estimated_cost, :start, :end, :status, :trip_price, :discount_type, :discount_value, :final_price, :paid_amount, :payment_status, :payment_method, :payment_ref, :note)");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':customer_id', $customerId);
$stmt->bindValue(':vehicle_id', $vehicleId);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':driver_name', $driverName);
$stmt->bindValue(':distance_km', $distanceKm);
$stmt->bindValue(':fuel_estimated_cost', $fuelEstimatedCost);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':trip_price', $tripPrice);
$stmt->bindValue(':discount_type', $discountType);
$stmt->bindValue(':discount_value', $discountValue);
$stmt->bindValue(':final_price', $finalPrice);
$stmt->bindValue(':paid_amount', $paidAmount);
$stmt->bindValue(':payment_status', $paymentStatus);
$stmt->bindValue(':payment_method', $paymentMethod !== '' ? $paymentMethod : null);
$stmt->bindValue(':payment_ref', $paymentRef !== '' ? $paymentRef : null);
$stmt->bindValue(':note', $note);
$stmt->execute();

$newId = intval($db->lastInsertId());

foreach ($tripCosts as $costItem) {
    $costStmt = $db->prepare("INSERT INTO trip_costs (tenant_id, trip_reservation_id, cost_type, description, amount, incurred_at) VALUES (:tenant_id, :trip_reservation_id, :cost_type, :description, :amount, :incurred_at)");
    $costStmt->bindValue(':tenant_id', $tenantId);
    $costStmt->bindValue(':trip_reservation_id', $newId);
    $costStmt->bindValue(':cost_type', $costItem->costType);
    $costStmt->bindValue(':description', $costItem->description);
    $costStmt->bindValue(':amount', $costItem->amount);
    $costStmt->bindValue(':incurred_at', $costItem->incurredAt);
    $costStmt->execute();
}

$invoiceNo = sprintf("TRIP-%d-%d", intval($tenantId), $newId);
$paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
$invoiceStmt = $db->prepare("INSERT INTO trip_invoices (tenant_id, trip_reservation_id, invoice_no, total_amount, paid_amount, payment_status, payment_method, payment_ref, paid_at, note) VALUES (:tenant_id, :trip_reservation_id, :invoice_no, :total_amount, :paid_amount, :payment_status, :payment_method, :payment_ref, :paid_at, :note)");
$invoiceStmt->bindValue(':tenant_id', $tenantId);
$invoiceStmt->bindValue(':trip_reservation_id', $newId);
$invoiceStmt->bindValue(':invoice_no', $invoiceNo);
$invoiceStmt->bindValue(':total_amount', $finalPrice);
$invoiceStmt->bindValue(':paid_amount', $paidAmount);
$invoiceStmt->bindValue(':payment_status', $paymentStatus);
$invoiceStmt->bindValue(':payment_method', $paymentMethod !== '' ? $paymentMethod : null);
$invoiceStmt->bindValue(':payment_ref', $paymentRef !== '' ? $paymentRef : null);
$invoiceStmt->bindValue(':paid_at', $paidAt);
$invoiceStmt->bindValue(':note', $note !== '' ? $note : null);
$invoiceStmt->execute();

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->tenantId = intval($tenantId);
$response->status = $status;
$response->distanceKm = $distanceKm;
$response->fuelEstimatedCost = $fuelEstimatedCost;
$response->tripPrice = $tripPrice;
$response->discountType = $discountType;
$response->discountValue = $discountValue;
$response->finalPrice = $finalPrice;
$response->paymentMethod = $paymentMethod;
$response->paymentRef = $paymentRef;
$response->tripCosts = $tripCosts;
$response->tripCostsTotal = $tripCostsTotal;
$response->profit = $finalPrice - $response->tripCostsTotal;
$response->customerId = $customerId;
$response->customer = $customerPayload;

header('Content-Type: application/json');
echo json_encode($response);

