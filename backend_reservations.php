<?php
require_once '_db.php';

$start = $_GET['start'];
$end = $_GET['end'];
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$stmt = $db->prepare("SELECT r.*, c.full_name AS customer_full_name, c.phone_number AS customer_phone_number, c.id_type AS customer_id_type, c.id_number AS customer_id_number, c.birthday AS customer_birthday
                      FROM reservations r
                      LEFT JOIN customers c ON c.id = r.customer_id AND c.tenant_id = r.tenant_id
                      WHERE r.tenant_id = :tenant_id AND NOT ((r.`end` <= :start) OR (r.start >= :end))");
$stmt->bindValue(':tenant_id', $tenantId);
$stmt->bindParam(':start', $start);
$stmt->bindParam(':end', $end);
$stmt->execute();
$result = $stmt->fetchAll();

$reservationIds = [];
foreach ($result as $row) {
    $reservationIds[] = intval($row['id']);
}

$feesByReservation = [];
if (!empty($reservationIds)) {
    $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
    $feeSql = "SELECT reservation_id, fee_type, charge_mode, description, meter_start, meter_end, period_start, period_end, amount
               FROM reservation_service_fees
               WHERE tenant_id = ? AND reservation_id IN ($placeholders)";
    $feeStmt = $db->prepare($feeSql);
    $idx = 1;
    $feeStmt->bindValue($idx++, $tenantId);
    foreach ($reservationIds as $reservationId) {
        $feeStmt->bindValue($idx++, $reservationId);
    }
    $feeStmt->execute();
    $feeRows = $feeStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($feeRows as $feeRow) {
        $reservationId = intval($feeRow['reservation_id']);
        if (!isset($feesByReservation[$reservationId])) {
            $feesByReservation[$reservationId] = [];
        }
        $item = new stdClass();
        $item->feeType = $feeRow['fee_type'];
        $item->chargeMode = isset($feeRow['charge_mode']) ? $feeRow['charge_mode'] : 'one_time';
        $item->description = isset($feeRow['description']) ? $feeRow['description'] : '';
        $item->meterStart = floatval($feeRow['meter_start']);
        $item->meterEnd = floatval($feeRow['meter_end']);
        $item->periodStart = $feeRow['period_start'];
        $item->periodEnd = $feeRow['period_end'];
        $item->amount = floatval($feeRow['amount']);
        $feesByReservation[$reservationId][] = $item;
    }
}

$invoicesByReservation = [];
if (!empty($reservationIds)) {
    $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
    $invoiceSql = "SELECT id, reservation_id, cycle_index, period_start, period_end, occupied_days, month_days, room_amount, service_amount, total_amount, payment_status, payment_method, paid_amount, paid_at, payment_ref, payment_note
                   FROM reservation_invoices
                   WHERE tenant_id = ? AND reservation_id IN ($placeholders)
                   ORDER BY cycle_index ASC, id ASC";
    $invoiceStmt = $db->prepare($invoiceSql);
    $idx = 1;
    $invoiceStmt->bindValue($idx++, $tenantId);
    foreach ($reservationIds as $reservationId) {
        $invoiceStmt->bindValue($idx++, $reservationId);
    }
    $invoiceStmt->execute();
    $invoiceRows = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoiceRows as $invoiceRow) {
        $reservationId = intval($invoiceRow['reservation_id']);
        if (!isset($invoicesByReservation[$reservationId])) {
            $invoicesByReservation[$reservationId] = [];
        }
        $item = new stdClass();
        $item->id = intval($invoiceRow['id']);
        $item->cycleIndex = intval($invoiceRow['cycle_index']);
        $item->periodStart = $invoiceRow['period_start'];
        $item->periodEnd = $invoiceRow['period_end'];
        $item->occupiedDays = intval($invoiceRow['occupied_days']);
        $item->monthDays = intval($invoiceRow['month_days']);
        $item->roomAmount = floatval($invoiceRow['room_amount']);
        $item->serviceAmount = floatval($invoiceRow['service_amount']);
        $item->totalAmount = floatval($invoiceRow['total_amount']);
        $item->paymentStatus = $invoiceRow['payment_status'];
        $item->paymentMethod = $invoiceRow['payment_method'];
        $item->paidAmount = floatval($invoiceRow['paid_amount']);
        $item->paidAt = $invoiceRow['paid_at'];
        $item->paymentRef = $invoiceRow['payment_ref'];
        $item->paymentNote = $invoiceRow['payment_note'];
        $invoicesByReservation[$reservationId][] = $item;
    }
}

$invoiceFeesByReservation = [];
foreach ($reservationIds as $reservationId) {
    $invoiceFeesByReservation[$reservationId] = fetchInvoiceServiceFeesByReservation($db, $tenantId, $reservationId);
}

#[AllowDynamicProperties]
class Event {}
$events = array();

date_default_timezone_set("UTC");
$now = new DateTime("now");
$today = $now->setTime(0, 0, 0);

foreach($result as $row) {
    $e = new Event();
    $e->id = $row['id'];
    $e->tenantId = intval($row['tenant_id']);
    $e->text = $row['name'];
    $e->start = $row['start'];
    $e->end = $row['end'];
    $e->resource = $row['room_id'];
    $e->bubbleHtml = "Reservation details: <br/>".$e->text;
    
    // additional properties
    $e->status = $row['status'];
    $reservationId = intval($row['id']);
    $reservationInvoices = isset($invoicesByReservation[$reservationId]) ? $invoicesByReservation[$reservationId] : [];
    $invoiceTotal = 0;
    $invoicePaid = 0;
    foreach ($reservationInvoices as $invoiceItem) {
        $invoiceTotal += floatval($invoiceItem->totalAmount);
        $invoicePaid += floatval($invoiceItem->paidAmount);
    }
    $paidPercent = $invoiceTotal > 0 ? intval(round(min(100, ($invoicePaid / $invoiceTotal) * 100))) : intval($row['paid']);
    $e->paid = $paidPercent;
    $e->roomPrice = floatval($row['room_price']);
    $e->discountType = isset($row['discount_type']) ? $row['discount_type'] : 'fixed';
    $e->discountValue = floatval($row['discount_value']);
    $e->finalPrice = floatval($row['final_price']);
    $e->customer = new stdClass();
    $e->customer->fullName = isset($row['customer_full_name']) ? $row['customer_full_name'] : '';
    $e->customer->phoneNumber = isset($row['customer_phone_number']) ? $row['customer_phone_number'] : '';
    $e->customer->idType = isset($row['customer_id_type']) ? $row['customer_id_type'] : 'CCCD';
    $e->customer->idNumber = isset($row['customer_id_number']) ? $row['customer_id_number'] : '';
    $e->customer->birthday = isset($row['customer_birthday']) ? $row['customer_birthday'] : null;
    $e->serviceFees = isset($feesByReservation[$reservationId]) ? $feesByReservation[$reservationId] : [];
    foreach ($reservationInvoices as $invoiceItem) {
        $invoiceId = intval($invoiceItem->id);
        $invoiceItem->serviceFees = isset($invoiceFeesByReservation[$reservationId][$invoiceId]) ? $invoiceFeesByReservation[$reservationId][$invoiceId] : [];
    }
    $e->invoices = $reservationInvoices;
    $events[] = $e;
}

header('Content-Type: application/json');
echo json_encode($events);
