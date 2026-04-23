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
if ($discountValue < 0) {
    $discountValue = 0;
}

$priceStmt = $db->prepare("SELECT price FROM rooms WHERE id = :id AND tenant_id = :tenant_id");
$priceStmt->bindValue(':id', $room);
$priceStmt->bindValue(':tenant_id', $tenantId);
$priceStmt->execute();
$roomData = $priceStmt->fetch(PDO::FETCH_ASSOC);
$roomPrice = $roomData ? floatval($roomData['price']) : 0;

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
$invoicePlans = buildReservationInvoices($start, $end, $monthlyNetAmount, $serviceFees);
$finalPrice = calculateReservationTotalFromInvoices($invoicePlans);

$customerId = upsertReservationCustomer($db, $tenantId, $customerPayload);

$stmt = $db->prepare("INSERT INTO reservations (tenant_id, customer_id, name, start, `end`, room_id, status, paid, room_price, discount_type, discount_value, final_price) VALUES (:tenant_id, :customer_id, :name, :start, :end, :room, 'New', 0, :room_price, :discount_type, :discount_value, :final_price)");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':customer_id', $customerId);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
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
reseedReservationInvoiceServiceFees($db, $tenantId, $newId, $invoices, $serviceFees);
recomputeReservationInvoicesFromServiceFees($db, $tenantId, $newId);
$invoices = fetchReservationInvoices($db, $tenantId, $newId);

$response = new stdClass();
$response->result = 'OK';
$response->message = 'Created with id: '.$newId;
$response->id = $newId;
$response->tenantId = $tenantId;
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

header('Content-Type: application/json');
echo json_encode($response);
