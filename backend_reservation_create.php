<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$start = $params->start;
$end = $params->end;
$name = $params->text;
$room = $params->resource;
$discountType = isset($params->discountType) ? $params->discountType : 'fixed';
$discountValue = isset($params->discountValue) ? floatval($params->discountValue) : 0;
$customerPayload = extractCustomerPayload($params);
$serviceFees = extractServiceFeesPayload($params);
$serviceFees = applyAutoChargeModeByDuration($serviceFees, $start, $end);
$contractTerms = extractContractTermsPayload($params);
$invoiceServicePayloads = (isset($params->invoiceServiceFees) && is_array($params->invoiceServiceFees)) ? $params->invoiceServiceFees : [];
$rentalType = resolveRentalTypeByRange($start, $end);
if ($discountValue < 0) {
    $discountValue = 0;
}

$overlapStmt = $db->prepare("SELECT id FROM reservations WHERE tenant_id = :tenant_id AND room_id = :room_id AND NOT ((`end` <= :start) OR (start >= :end)) LIMIT 1");
$overlapStmt->bindValue(':tenant_id', $tenantId);
$overlapStmt->bindValue(':room_id', $room);
$overlapStmt->bindValue(':start', $start);
$overlapStmt->bindValue(':end', $end);
$overlapStmt->execute();
$overlapId = $overlapStmt->fetchColumn();
if ($overlapId) {
    $error = new stdClass();
    $error->result = 'Error';
    $error->message = 'Phòng đã có đặt/thuê trong khoảng thời gian này.';
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

$priceStmt = $db->prepare("SELECT price, price_day, price_hour FROM rooms WHERE id = :id AND tenant_id = :tenant_id");
$priceStmt->bindValue(':id', $room);
$priceStmt->bindValue(':tenant_id', $tenantId);
$priceStmt->execute();
$roomData = $priceStmt->fetch(PDO::FETCH_ASSOC);
$roomPrice = $roomData ? floatval($roomData['price']) : 0;
$roomPriceDay = $roomData && isset($roomData['price_day']) ? floatval($roomData['price_day']) : 0;
$roomPriceHour = $roomData && isset($roomData['price_hour']) ? floatval($roomData['price_hour']) : 0;
$settingsStmt = $db->prepare("SELECT short_term_day_threshold_hours FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$settingsStmt->bindValue(':tenant_id', $tenantId);
$settingsStmt->execute();
$dayThresholdHours = intval($settingsStmt->fetchColumn());
if ($dayThresholdHours < 1) {
    $dayThresholdHours = 4;
}
if ($rentalType === 'short_term' && ($roomPriceDay <= 0 || $roomPriceHour <= 0)) {
    http_response_code(422);
    $error = new stdClass();
    $error->result = 'Error';
    $error->message = 'Giá ngày và giá giờ là bắt buộc cho đặt/thuê ngắn hạn.';
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

if ($discountType === 'percent') {
    if ($discountValue > 100) {
        $discountValue = 100;
    }
    $finalPrice = $roomPrice - ($roomPrice * $discountValue / 100);
} else {
    $discountType = 'fixed';
$finalPrice = $roomPrice - $discountValue;
}

if ($finalPrice < 0) {
    $finalPrice = 0;
}

$monthlyNetAmount = calculateRoomMonthlyAmount($roomPrice, $discountType, $discountValue);
$shortTermAmount = calculateShortTermRoomAmount($start, $end, $roomPriceDay, $roomPriceHour, $dayThresholdHours);
$baseRoomAmount = isMonthlyCycleRange($start, $end) ? $monthlyNetAmount : $shortTermAmount;
$invoicePlans = buildReservationInvoices($start, $end, $baseRoomAmount, $serviceFees);
$finalPrice = calculateReservationTotalFromInvoices($invoicePlans);

$customerId = upsertReservationCustomer($db, $tenantId, $customerPayload);

$stmt = $db->prepare("INSERT INTO reservations (tenant_id, customer_id, name, start, `end`, rental_type, room_id, status, paid, room_price, discount_type, discount_value, final_price) VALUES (:tenant_id, :customer_id, :name, :start, :end, :rental_type, :room, 'New', 0, :room_price, :discount_type, :discount_value, :final_price)");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':customer_id', $customerId);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->bindValue(':rental_type', $rentalType);
$stmt->bindValue(':name', $name);
$stmt->bindValue(':room', $room);
$stmt->bindValue(':room_price', $roomPrice);
$stmt->bindValue(':discount_type', $discountType);
$stmt->bindValue(':discount_value', $discountValue);
$stmt->bindValue(':final_price', $finalPrice);
$stmt->execute();

$newId = $db->lastInsertId();
replaceReservationServiceFees($db, $tenantId, $newId, $serviceFees);
$invoices = replaceReservationInvoices($db, $tenantId, $newId, $invoicePlans);
upsertReservationContractTerms($db, $tenantId, $newId, $contractTerms, isMonthlyCycleRange($start, $end));
reseedReservationInvoiceServiceFees($db, $tenantId, $newId, $invoices, $serviceFees);
applyInvoiceServiceFeePayloadsByCycle($db, $tenantId, $newId, $invoiceServicePayloads);
recomputeReservationInvoicesFromServiceFees($db, $tenantId, $newId);
$invoices = fetchReservationInvoices($db, $tenantId, $newId);
$invoiceFeesMap = fetchInvoiceServiceFeesByReservation($db, $tenantId, $newId);
foreach ($invoices as &$invoiceItem) {
    $invoiceId = isset($invoiceItem['id']) ? intval($invoiceItem['id']) : 0;
    $invoiceItem['serviceFees'] = ($invoiceId > 0 && isset($invoiceFeesMap[$invoiceId])) ? $invoiceFeesMap[$invoiceId] : [];
}
unset($invoiceItem);
$savedContractTerms = fetchReservationContractTerms($db, $tenantId, $newId);

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->tenantId = $tenantId;
$response->rentalType = $rentalType;
$response->status = "New";
$response->paid = 0;
$response->roomPrice = $roomPrice;
$response->discountType = $discountType;
$response->discountValue = $discountValue;
$response->finalPrice = calculateReservationTotalFromInvoices(array_map(function ($item) {
    return [
        'total_amount' => isset($item['totalAmount']) ? $item['totalAmount'] : 0
    ];
}, $invoices));
$response->serviceFeesTotal = calculateServiceFeesTotalAmount($serviceFees);
$response->customerId = $customerId;
$response->customer = $customerPayload;
$response->serviceFees = $serviceFees;
$response->invoices = $invoices;
$response->contractTerms = $savedContractTerms;

header('Content-Type: application/json');
echo json_encode($response);
