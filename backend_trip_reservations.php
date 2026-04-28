<?php
require_once '_db.php';

$start = isset($_GET['start']) ? $_GET['start'] : null;
$end = isset($_GET['end']) ? $_GET['end'] : null;
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$settingStmt = $db->prepare("SELECT transport_fuel_price_per_liter FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1");
$settingStmt->bindValue(':tenant_id', $tenantId);
$settingStmt->execute();
$settingRow = $settingStmt->fetch(PDO::FETCH_ASSOC);
$fuelPricePerLiter = $settingRow && isset($settingRow['transport_fuel_price_per_liter']) ? floatval($settingRow['transport_fuel_price_per_liter']) : 22000;
if ($fuelPricePerLiter <= 0) {
    $fuelPricePerLiter = 22000;
}

$stmt = $db->prepare("SELECT t.*, c.full_name AS customer_full_name, c.phone_number AS customer_phone_number, c.id_type AS customer_id_type, c.id_number AS customer_id_number, c.birthday AS customer_birthday
                      FROM trip_reservations t
                      LEFT JOIN customers c ON c.id = t.customer_id AND c.tenant_id = t.tenant_id
                      WHERE t.tenant_id = :tenant_id AND NOT ((t.`end` <= :start) OR (t.start >= :end))");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->execute();
$rows = $stmt->fetchAll();

$reservationIds = [];
$vehicleIds = [];
foreach ($rows as $row) {
    $reservationIds[] = intval($row['id']);
    $vehicleIds[] = intval($row['vehicle_id']);
}

$costMap = [];
if (!empty($reservationIds)) {
    $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
    $costStmt = $db->prepare("SELECT trip_reservation_id, cost_type, description, amount, incurred_at FROM trip_costs WHERE tenant_id = ? AND trip_reservation_id IN ($placeholders)");
    $idx = 1;
    $costStmt->bindValue($idx++, $tenantId);
    foreach ($reservationIds as $reservationId) {
        $costStmt->bindValue($idx++, $reservationId);
    }
    $costStmt->execute();
    $costRows = $costStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($costRows as $costRow) {
        $reservationId = intval($costRow['trip_reservation_id']);
        if (!isset($costMap[$reservationId])) {
            $costMap[$reservationId] = [];
        }
        $item = new stdClass();
        $item->costType = $costRow['cost_type'];
        $item->description = isset($costRow['description']) ? $costRow['description'] : '';
        $item->amount = floatval($costRow['amount']);
        $item->incurredAt = $costRow['incurred_at'];
        $costMap[$reservationId][] = $item;
    }
}

$invoiceMap = [];
if (!empty($reservationIds)) {
    $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
    $invoiceStmt = $db->prepare("SELECT trip_reservation_id, invoice_no, total_amount, paid_amount, payment_status, payment_method, payment_ref, paid_at, note FROM trip_invoices WHERE tenant_id = ? AND trip_reservation_id IN ($placeholders) ORDER BY id DESC");
    $idx = 1;
    $invoiceStmt->bindValue($idx++, $tenantId);
    foreach ($reservationIds as $reservationId) {
        $invoiceStmt->bindValue($idx++, $reservationId);
    }
    $invoiceStmt->execute();
    $invoiceRows = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoiceRows as $invoiceRow) {
        $reservationId = intval($invoiceRow['trip_reservation_id']);
        if (isset($invoiceMap[$reservationId])) {
            continue;
        }
        $inv = new stdClass();
        $inv->invoiceNo = isset($invoiceRow['invoice_no']) ? $invoiceRow['invoice_no'] : null;
        $inv->totalAmount = isset($invoiceRow['total_amount']) ? floatval($invoiceRow['total_amount']) : 0;
        $inv->paidAmount = isset($invoiceRow['paid_amount']) ? floatval($invoiceRow['paid_amount']) : 0;
        $inv->paymentStatus = isset($invoiceRow['payment_status']) ? $invoiceRow['payment_status'] : 'unpaid';
        $inv->paymentMethod = isset($invoiceRow['payment_method']) ? $invoiceRow['payment_method'] : null;
        $inv->paymentRef = isset($invoiceRow['payment_ref']) ? $invoiceRow['payment_ref'] : null;
        $inv->paidAt = isset($invoiceRow['paid_at']) ? $invoiceRow['paid_at'] : null;
        $inv->note = isset($invoiceRow['note']) ? $invoiceRow['note'] : null;
        $invoiceMap[$reservationId] = $inv;
    }
}

$vehicleMap = [];
if (!empty($vehicleIds)) {
    $vehicleIds = array_values(array_unique(array_filter($vehicleIds, function ($id) {
        return intval($id) > 0;
    })));
    if (!empty($vehicleIds)) {
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        $vehicleStmt = $db->prepare("SELECT id, base_price, fuel_consumption_per_100km FROM vehicles WHERE tenant_id = ? AND id IN ($placeholders)");
        $idx = 1;
        $vehicleStmt->bindValue($idx++, $tenantId);
        foreach ($vehicleIds as $vehicleId) {
            $vehicleStmt->bindValue($idx++, intval($vehicleId));
        }
        $vehicleStmt->execute();
        $vehicleRows = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vehicleRows as $vehicleRow) {
            $vehicleMap[intval($vehicleRow['id'])] = [
                'base_price' => isset($vehicleRow['base_price']) ? floatval($vehicleRow['base_price']) : 6000,
                'fuel_consumption_per_100km' => isset($vehicleRow['fuel_consumption_per_100km']) ? floatval($vehicleRow['fuel_consumption_per_100km']) : 8,
            ];
        }
    }
}

$events = [];
foreach ($rows as $row) {
    $event = new stdClass();
    $event->id = intval($row['id']);
    $event->tenantId = intval($row['tenant_id']);
    $event->text = $row['name'];
    $event->start = $row['start'];
    $event->end = $row['end'];
    $event->resource = intval($row['vehicle_id']);
    $event->status = isset($row['status']) ? $row['status'] : 'New';
    $event->driverName = isset($row['driver_name']) ? $row['driver_name'] : '';
    $event->distanceKm = isset($row['distance_km']) ? floatval($row['distance_km']) : 0;
    $event->tripPrice = isset($row['trip_price']) ? floatval($row['trip_price']) : 0;
    $event->fuelEstimatedCost = isset($row['fuel_estimated_cost']) ? floatval($row['fuel_estimated_cost']) : 0;
    $event->discountType = isset($row['discount_type']) ? $row['discount_type'] : 'fixed';
    $event->discountValue = floatval($row['discount_value']);
    $event->finalPrice = isset($row['final_price']) ? floatval($row['final_price']) : 0;
    $invoice = isset($invoiceMap[intval($row['id'])]) ? $invoiceMap[intval($row['id'])] : null;
    $event->paidAmount = $invoice ? $invoice->paidAmount : floatval($row['paid_amount']);
    $event->paymentStatus = $invoice ? $invoice->paymentStatus : (isset($row['payment_status']) ? $row['payment_status'] : 'unpaid');
    $event->paymentMethod = $invoice ? $invoice->paymentMethod : (isset($row['payment_method']) ? $row['payment_method'] : null);
    $event->paymentRef = $invoice ? $invoice->paymentRef : (isset($row['payment_ref']) ? $row['payment_ref'] : null);
    $event->invoice = $invoice;
    $event->note = isset($row['note']) ? $row['note'] : '';
    $event->customer = new stdClass();
    $event->customer->fullName = isset($row['customer_full_name']) ? $row['customer_full_name'] : '';
    $event->customer->phoneNumber = isset($row['customer_phone_number']) ? $row['customer_phone_number'] : '';
    $event->customer->idType = isset($row['customer_id_type']) ? $row['customer_id_type'] : 'CCCD';
    $event->customer->idNumber = isset($row['customer_id_number']) ? $row['customer_id_number'] : '';
    $event->customer->birthday = isset($row['customer_birthday']) ? $row['customer_birthday'] : null;
    $reservationId = intval($row['id']);
    $event->tripCosts = isset($costMap[$reservationId]) ? $costMap[$reservationId] : [];
    $event->tripCostsTotal = calculateTripCostsTotal($event->tripCosts) + $event->fuelEstimatedCost;
    $vehicleInfo = isset($vehicleMap[$event->resource]) ? $vehicleMap[$event->resource] : null;
    if ($vehicleInfo) {
        $basePricePerKm = isset($vehicleInfo['base_price']) ? floatval($vehicleInfo['base_price']) : 0;
        if ($basePricePerKm <= 0) {
            $basePricePerKm = 6000;
        }
        $fuelLitersPer100Km = isset($vehicleInfo['fuel_consumption_per_100km']) ? floatval($vehicleInfo['fuel_consumption_per_100km']) : 0;
        if ($fuelLitersPer100Km <= 0) {
            $fuelLitersPer100Km = 8;
        }
        $expectedTripPrice = round(max(0, $event->distanceKm) * $basePricePerKm, 2);
        $expectedFuelEstimatedCost = round((max(0, $event->distanceKm) * $fuelLitersPer100Km / 100) * $fuelPricePerLiter, 2);
        $expectedTripCostsTotal = calculateTripCostsTotal($event->tripCosts) + $expectedFuelEstimatedCost;
        $expectedFinalPrice = computeTripFinalTotal($expectedTripPrice, $expectedTripCostsTotal, $event->discountType, $event->discountValue);

        $hasMismatch =
            abs($event->tripPrice - $expectedTripPrice) > 0.009 ||
            abs($event->fuelEstimatedCost - $expectedFuelEstimatedCost) > 0.009 ||
            abs($event->finalPrice - $expectedFinalPrice) > 0.009;

        if ($hasMismatch) {
            $event->dataNeedsRecalculation = true;
            $event->recalculated = new stdClass();
            $event->recalculated->tripPrice = $expectedTripPrice;
            $event->recalculated->fuelEstimatedCost = $expectedFuelEstimatedCost;
            $event->recalculated->tripCostsTotal = $expectedTripCostsTotal;
            $event->recalculated->finalPrice = $expectedFinalPrice;
            $event->warningMessage = 'Du lieu gia chuyen dang lech cong thuc, vui long mo va luu lai de cap nhat.';
        }
    }
    $event->profit = $event->finalPrice - $event->tripCostsTotal;
    $events[] = $event;
}

header('Content-Type: application/json');
echo json_encode($events);

