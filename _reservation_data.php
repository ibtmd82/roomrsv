<?php

function normalizeDateValue($value)
{
    if ($value === null) {
        return null;
    }
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }

    try {
        $dt = new DateTime($text);
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function normalizeNumberValue($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    return floatval($value);
}

function isMonthlyCycleRange($start, $end)
{
    if (!$start || !$end) {
        return false;
    }
    try {
        $startDt = new DateTime($start);
        $endDt = new DateTime($end);
    } catch (Throwable $e) {
        return false;
    }
    if ($endDt <= $startDt) {
        return false;
    }
    $oneMonthAfterStart = (clone $startDt)->modify('+1 month');
    return $endDt >= $oneMonthAfterStart;
}

function applyAutoChargeModeByDuration($fees, $start, $end)
{
    if (!is_array($fees)) {
        return [];
    }
    $mode = isMonthlyCycleRange($start, $end) ? 'per_cycle' : 'one_time';
    $result = [];
    foreach ($fees as $fee) {
        if (!is_array($fee)) {
            continue;
        }
        $fee['charge_mode'] = $mode;
        $result[] = $fee;
    }
    return $result;
}

function extractCustomerPayload($params)
{
    if (!isset($params->customer) || !is_object($params->customer)) {
        return null;
    }

    $customer = $params->customer;
    $fullName = isset($customer->fullName) ? trim((string)$customer->fullName) : '';
    $phoneNumber = isset($customer->phoneNumber) ? trim((string)$customer->phoneNumber) : '';
    $idType = isset($customer->idType) ? trim((string)$customer->idType) : 'CCCD';
    $idNumber = isset($customer->idNumber) ? trim((string)$customer->idNumber) : '';
    $birthday = normalizeDateValue(isset($customer->birthday) ? $customer->birthday : null);

    if ($fullName === '' && $phoneNumber === '' && $idNumber === '') {
        return null;
    }

    if ($idType !== 'CCCD' && $idType !== 'Passport') {
        $idType = 'CCCD';
    }

    return [
        'full_name' => $fullName,
        'phone_number' => $phoneNumber,
        'id_type' => $idType,
        'id_number' => $idNumber,
        'birthday' => $birthday
    ];
}

function extractServiceFeesPayload($params)
{
    $result = [];
    if (!isset($params->serviceFees) || !is_array($params->serviceFees)) {
        return $result;
    }

    foreach ($params->serviceFees as $fee) {
        if (!is_object($fee)) {
            continue;
        }
        $feeType = isset($fee->feeType) ? trim((string)$fee->feeType) : '';
        if (!in_array($feeType, ['electricity', 'water', 'other'], true)) {
            continue;
        }

        $meterStart = normalizeNumberValue(isset($fee->meterStart) ? $fee->meterStart : 0);
        $meterEnd = normalizeNumberValue(isset($fee->meterEnd) ? $fee->meterEnd : 0);
        $amount = normalizeNumberValue(isset($fee->amount) ? $fee->amount : 0);
        $periodStart = normalizeDateValue(isset($fee->periodStart) ? $fee->periodStart : null);
        $periodEnd = normalizeDateValue(isset($fee->periodEnd) ? $fee->periodEnd : null);
        $description = isset($fee->description) ? trim((string)$fee->description) : '';

        $result[] = [
            'fee_type' => $feeType,
            'charge_mode' => (isset($fee->chargeMode) && $fee->chargeMode === 'per_cycle') ? 'per_cycle' : 'one_time',
            'description' => $description,
            'meter_start' => $meterStart,
            'meter_end' => $meterEnd,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'amount' => max(0, $amount)
        ];
    }

    return $result;
}

function upsertReservationCustomer($db, $tenantId, $customerPayload, $existingCustomerId = null)
{
    if ($customerPayload === null) {
        return null;
    }

    if ($existingCustomerId !== null) {
        $stmt = $db->prepare("UPDATE customers SET full_name = :full_name, phone_number = :phone_number, id_type = :id_type, id_number = :id_number, birthday = :birthday WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->bindValue(':id', $existingCustomerId);
        $stmt->bindValue(':tenant_id', $tenantId);
        $stmt->bindValue(':full_name', $customerPayload['full_name']);
        $stmt->bindValue(':phone_number', $customerPayload['phone_number']);
        $stmt->bindValue(':id_type', $customerPayload['id_type']);
        $stmt->bindValue(':id_number', $customerPayload['id_number']);
        $stmt->bindValue(':birthday', $customerPayload['birthday']);
        $stmt->execute();
        return intval($existingCustomerId);
    }

    $stmt = $db->prepare("INSERT INTO customers (tenant_id, full_name, phone_number, id_type, id_number, birthday) VALUES (:tenant_id, :full_name, :phone_number, :id_type, :id_number, :birthday)");
    $stmt->bindValue(':tenant_id', $tenantId);
    $stmt->bindValue(':full_name', $customerPayload['full_name']);
    $stmt->bindValue(':phone_number', $customerPayload['phone_number']);
    $stmt->bindValue(':id_type', $customerPayload['id_type']);
    $stmt->bindValue(':id_number', $customerPayload['id_number']);
    $stmt->bindValue(':birthday', $customerPayload['birthday']);
    $stmt->execute();

    return intval($db->lastInsertId());
}

function replaceReservationServiceFees($db, $tenantId, $reservationId, $fees)
{
    $deleteStmt = $db->prepare("DELETE FROM reservation_service_fees WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $deleteStmt->bindValue(':tenant_id', $tenantId);
    $deleteStmt->bindValue(':reservation_id', $reservationId);
    $deleteStmt->execute();

    if (empty($fees)) {
        return;
    }

    $insertStmt = $db->prepare("INSERT INTO reservation_service_fees (tenant_id, reservation_id, invoice_id, fee_type, charge_mode, description, meter_start, meter_end, period_start, period_end, amount) VALUES (:tenant_id, :reservation_id, :invoice_id, :fee_type, :charge_mode, :description, :meter_start, :meter_end, :period_start, :period_end, :amount)");
    foreach ($fees as $fee) {
        $insertStmt->bindValue(':tenant_id', $tenantId);
        $insertStmt->bindValue(':reservation_id', $reservationId);
        $insertStmt->bindValue(':invoice_id', null);
        $insertStmt->bindValue(':fee_type', $fee['fee_type']);
        $insertStmt->bindValue(':charge_mode', isset($fee['charge_mode']) ? $fee['charge_mode'] : 'one_time');
        $insertStmt->bindValue(':description', $fee['description']);
        $insertStmt->bindValue(':meter_start', $fee['meter_start']);
        $insertStmt->bindValue(':meter_end', $fee['meter_end']);
        $insertStmt->bindValue(':period_start', $fee['period_start']);
        $insertStmt->bindValue(':period_end', $fee['period_end']);
        $insertStmt->bindValue(':amount', $fee['amount']);
        $insertStmt->execute();
    }
}

function calculateServiceFeesTotalAmount($fees)
{
    $total = 0;
    if (!is_array($fees)) {
        return $total;
    }
    foreach ($fees as $fee) {
        if (!is_array($fee)) {
            continue;
        }
        $amount = isset($fee['amount']) ? floatval($fee['amount']) : 0;
        if ($amount > 0) {
            $total += $amount;
        }
    }
    return $total;
}

function calculateRoomMonthlyAmount($roomPrice, $discountType, $discountValue)
{
    $roomPrice = floatval($roomPrice);
    $discountValue = max(0, floatval($discountValue));
    if ($discountType === 'percent') {
        $discountValue = min(100, $discountValue);
        $result = $roomPrice - ($roomPrice * $discountValue / 100);
    } else {
        $result = $roomPrice - $discountValue;
    }
    return max(0, $result);
}

function buildReservationInvoices($start, $end, $monthlyNetAmount, $serviceFees)
{
    $items = [];
    if (!$start || !$end) {
        return $items;
    }
    try {
        $startDt = new DateTime($start);
        $endDt = new DateTime($end);
    } catch (Throwable $e) {
        return $items;
    }
    if ($endDt <= $startDt) {
        return $items;
    }

    $oneMonthAfterStart = (clone $startDt)->modify('+1 month');
    $isMonthlyCycle = $endDt >= $oneMonthAfterStart;
    $oneTimeTotal = 0;
    $perCycleAmount = 0;
    if (is_array($serviceFees)) {
        foreach ($serviceFees as $fee) {
            if (!is_array($fee)) {
                continue;
            }
            $amount = isset($fee['amount']) ? max(0, floatval($fee['amount'])) : 0;
            $mode = isset($fee['charge_mode']) && $fee['charge_mode'] === 'per_cycle' ? 'per_cycle' : 'one_time';
            if ($mode === 'per_cycle') {
                $perCycleAmount += $amount;
            } else {
                $oneTimeTotal += $amount;
            }
        }
    }

    if (!$isMonthlyCycle) {
        $days = max(1, intval($startDt->diff($endDt)->format('%a')));
        $serviceAmount = round(floatval($oneTimeTotal + $perCycleAmount), 2);
        $total = round(floatval($monthlyNetAmount) + $serviceAmount, 2);
        $items[] = [
            'cycle_index' => 1,
            'period_start' => $startDt->format('Y-m-d'),
            'period_end' => $endDt->format('Y-m-d'),
            'occupied_days' => $days,
            'month_days' => $days,
            'room_amount' => round(floatval($monthlyNetAmount), 2),
            'service_amount' => $serviceAmount,
            'total_amount' => $total
        ];
        return $items;
    }

    $currentMonthStart = (new DateTime('now'))->modify('first day of this month')->setTime(0, 0, 0);
    $nextMonthStart = (clone $currentMonthStart)->modify('first day of next month');
    $generationEnd = $endDt < $nextMonthStart ? clone $endDt : clone $nextMonthStart;
    if ($generationEnd <= $startDt) {
        return $items;
    }

    $cursor = clone $startDt;
    $index = 1;
    while ($cursor < $generationEnd) {
        $monthStart = (clone $cursor)->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = (clone $monthStart)->modify('first day of next month');
        $periodStart = clone $cursor;
        $periodEnd = $generationEnd < $monthEnd ? clone $generationEnd : clone $monthEnd;
        if ($periodEnd <= $periodStart) {
            break;
        }
        $occupiedDays = max(1, intval($periodStart->diff($periodEnd)->format('%a')));
        $daysInMonth = intval($monthStart->format('t'));
        $roomAmount = round((floatval($monthlyNetAmount) * $occupiedDays) / max(1, $daysInMonth), 2);
        $perCycleProrated = round((floatval($perCycleAmount) * $occupiedDays) / max(1, $daysInMonth), 2);
        $serviceAmount = $perCycleProrated + ($index === 1 ? round(floatval($oneTimeTotal), 2) : 0);
        $totalAmount = round($roomAmount + $serviceAmount, 2);
        $items[] = [
            'cycle_index' => $index,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'occupied_days' => $occupiedDays,
            'month_days' => $daysInMonth,
            'room_amount' => $roomAmount,
            'service_amount' => $serviceAmount,
            'total_amount' => $totalAmount
        ];
        $cursor = $periodEnd;
        $index += 1;
    }
    return $items;
}

function calculateReservationTotalFromInvoices($invoices)
{
    $total = 0;
    if (!is_array($invoices)) {
        return $total;
    }
    foreach ($invoices as $item) {
        if (!is_array($item)) {
            continue;
        }
        $total += isset($item['total_amount']) ? floatval($item['total_amount']) : 0;
    }
    return round($total, 2);
}

function replaceReservationInvoices($db, $tenantId, $reservationId, $invoices)
{
    $existingMap = [];
    $existingStmt = $db->prepare("SELECT cycle_index, payment_status, payment_method, paid_amount, paid_at, payment_ref, payment_note FROM reservation_invoices WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $existingStmt->bindValue(':tenant_id', $tenantId);
    $existingStmt->bindValue(':reservation_id', $reservationId);
    $existingStmt->execute();
    $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($existingRows as $row) {
        $existingMap[intval($row['cycle_index'])] = $row;
    }

    $deleteStmt = $db->prepare("DELETE FROM reservation_invoices WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $deleteStmt->bindValue(':tenant_id', $tenantId);
    $deleteStmt->bindValue(':reservation_id', $reservationId);
    $deleteStmt->execute();
    if (empty($invoices)) {
        return [];
    }

    $insertStmt = $db->prepare("INSERT INTO reservation_invoices (tenant_id, reservation_id, cycle_index, period_start, period_end, occupied_days, month_days, room_amount, service_amount, total_amount, payment_status, payment_method, paid_amount, paid_at, payment_ref, payment_note) VALUES (:tenant_id, :reservation_id, :cycle_index, :period_start, :period_end, :occupied_days, :month_days, :room_amount, :service_amount, :total_amount, :payment_status, :payment_method, :paid_amount, :paid_at, :payment_ref, :payment_note)");
    $saved = [];
    foreach ($invoices as $invoice) {
        $insertStmt->bindValue(':tenant_id', $tenantId);
        $insertStmt->bindValue(':reservation_id', $reservationId);
        $insertStmt->bindValue(':cycle_index', intval($invoice['cycle_index']));
        $insertStmt->bindValue(':period_start', $invoice['period_start']);
        $insertStmt->bindValue(':period_end', $invoice['period_end']);
        $insertStmt->bindValue(':occupied_days', intval($invoice['occupied_days']));
        $insertStmt->bindValue(':month_days', intval($invoice['month_days']));
        $insertStmt->bindValue(':room_amount', floatval($invoice['room_amount']));
        $insertStmt->bindValue(':service_amount', floatval($invoice['service_amount']));
        $insertStmt->bindValue(':total_amount', floatval($invoice['total_amount']));
        $existing = isset($existingMap[intval($invoice['cycle_index'])]) ? $existingMap[intval($invoice['cycle_index'])] : null;
        $paymentStatus = $existing ? $existing['payment_status'] : 'unpaid';
        $paymentMethod = $existing ? $existing['payment_method'] : null;
        $paidAt = $existing ? $existing['paid_at'] : null;
        $paymentRef = $existing ? $existing['payment_ref'] : null;
        $paymentNote = $existing ? $existing['payment_note'] : null;
        $paidAmount = $existing ? floatval($existing['paid_amount']) : 0;
        if ($paymentStatus !== 'paid') {
            $paymentStatus = 'unpaid';
            $paymentMethod = null;
            $paidAt = null;
            $paymentRef = null;
            $paymentNote = null;
            $paidAmount = 0;
        } else {
            $paidAmount = floatval($invoice['total_amount']);
        }
        $insertStmt->bindValue(':payment_status', $paymentStatus);
        $insertStmt->bindValue(':payment_method', $paymentMethod);
        $insertStmt->bindValue(':paid_amount', $paidAmount);
        $insertStmt->bindValue(':paid_at', $paidAt);
        $insertStmt->bindValue(':payment_ref', $paymentRef);
        $insertStmt->bindValue(':payment_note', $paymentNote);
        $insertStmt->execute();
        $saved[] = [
            'id' => intval($db->lastInsertId()),
            'cycleIndex' => intval($invoice['cycle_index']),
            'periodStart' => $invoice['period_start'],
            'periodEnd' => $invoice['period_end'],
            'occupiedDays' => intval($invoice['occupied_days']),
            'monthDays' => intval($invoice['month_days']),
            'roomAmount' => floatval($invoice['room_amount']),
            'serviceAmount' => floatval($invoice['service_amount']),
            'totalAmount' => floatval($invoice['total_amount']),
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'paidAmount' => $paidAmount,
            'paidAt' => $paidAt,
            'paymentRef' => $paymentRef,
            'paymentNote' => $paymentNote
        ];
    }
    return $saved;
}

function attachServiceFeesToInvoices($db, $tenantId, $reservationId)
{
    $invoiceStmt = $db->prepare("SELECT id FROM reservation_invoices WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id ORDER BY cycle_index ASC, id ASC LIMIT 1");
    $invoiceStmt->bindValue(':tenant_id', $tenantId);
    $invoiceStmt->bindValue(':reservation_id', $reservationId);
    $invoiceStmt->execute();
    $invoiceId = $invoiceStmt->fetchColumn();
    if (!$invoiceId) {
        return;
    }
    $clearStmt = $db->prepare("UPDATE reservation_service_fees SET invoice_id = NULL WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $clearStmt->bindValue(':tenant_id', $tenantId);
    $clearStmt->bindValue(':reservation_id', $reservationId);
    $clearStmt->execute();

    $updateStmt = $db->prepare("UPDATE reservation_service_fees
                                SET invoice_id = :invoice_id
                                WHERE tenant_id = :tenant_id
                                  AND reservation_id = :reservation_id
                                  AND (charge_mode IS NULL OR charge_mode = 'one_time')");
    $updateStmt->bindValue(':invoice_id', intval($invoiceId));
    $updateStmt->bindValue(':tenant_id', $tenantId);
    $updateStmt->bindValue(':reservation_id', $reservationId);
    $updateStmt->execute();
}

function reseedReservationInvoiceServiceFees($db, $tenantId, $reservationId, $invoiceRows, $serviceFees)
{
    $deleteStmt = $db->prepare("DELETE FROM reservation_service_fees WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $deleteStmt->bindValue(':tenant_id', $tenantId);
    $deleteStmt->bindValue(':reservation_id', $reservationId);
    $deleteStmt->execute();

    if (empty($invoiceRows)) {
        return;
    }

    $firstInvoiceId = intval($invoiceRows[0]['id']);
    $reservationStmt = $db->prepare("SELECT start, `end` FROM reservations WHERE id = :id AND tenant_id = :tenant_id");
    $reservationStmt->bindValue(':id', $reservationId);
    $reservationStmt->bindValue(':tenant_id', $tenantId);
    $reservationStmt->execute();
    $reservationRow = $reservationStmt->fetch(PDO::FETCH_ASSOC);
    $isPerCycleReservation = $reservationRow ? isMonthlyCycleRange($reservationRow['start'], $reservationRow['end']) : false;

    if ($isPerCycleReservation) {
        $hasElectric = false;
        $hasWater = false;
        foreach ($serviceFees as $fee) {
            if (!is_array($fee)) {
                continue;
            }
            if (isset($fee['fee_type']) && $fee['fee_type'] === 'electricity') {
                $hasElectric = true;
            }
            if (isset($fee['fee_type']) && $fee['fee_type'] === 'water') {
                $hasWater = true;
            }
        }
        if (!$hasElectric) {
            $serviceFees[] = [
                'fee_type' => 'electricity',
                'charge_mode' => 'per_cycle',
                'description' => '',
                'meter_start' => 0,
                'meter_end' => 0,
                'period_start' => null,
                'period_end' => null,
                'amount' => 0
            ];
        }
        if (!$hasWater) {
            $serviceFees[] = [
                'fee_type' => 'water',
                'charge_mode' => 'per_cycle',
                'description' => '',
                'meter_start' => 0,
                'meter_end' => 0,
                'period_start' => null,
                'period_end' => null,
                'amount' => 0
            ];
        }
    }
    $insertStmt = $db->prepare("INSERT INTO reservation_service_fees (tenant_id, reservation_id, invoice_id, fee_type, charge_mode, description, meter_start, meter_end, period_start, period_end, amount)
                                VALUES (:tenant_id, :reservation_id, :invoice_id, :fee_type, :charge_mode, :description, :meter_start, :meter_end, :period_start, :period_end, :amount)");

    foreach ($serviceFees as $fee) {
        if (!is_array($fee)) {
            continue;
        }
        $feeType = isset($fee['fee_type']) ? $fee['fee_type'] : null;
        if (!in_array($feeType, ['electricity', 'water', 'other'], true)) {
            continue;
        }
        $chargeMode = (isset($fee['charge_mode']) && $fee['charge_mode'] === 'per_cycle') ? 'per_cycle' : 'one_time';
        $description = isset($fee['description']) ? $fee['description'] : '';
        $periodStart = isset($fee['period_start']) ? $fee['period_start'] : null;
        $periodEnd = isset($fee['period_end']) ? $fee['period_end'] : null;
        $amount = max(0, isset($fee['amount']) ? floatval($fee['amount']) : 0);
        $baseMeterStart = max(0, isset($fee['meter_start']) ? floatval($fee['meter_start']) : 0);

        if ($chargeMode === 'one_time') {
            $insertStmt->bindValue(':tenant_id', $tenantId);
            $insertStmt->bindValue(':reservation_id', $reservationId);
            $insertStmt->bindValue(':invoice_id', $firstInvoiceId);
            $insertStmt->bindValue(':fee_type', $feeType);
            $insertStmt->bindValue(':charge_mode', 'one_time');
            $insertStmt->bindValue(':description', $description);
            $insertStmt->bindValue(':meter_start', $baseMeterStart);
            $insertStmt->bindValue(':meter_end', $baseMeterStart);
            $insertStmt->bindValue(':period_start', $periodStart);
            $insertStmt->bindValue(':period_end', $periodEnd);
            $insertStmt->bindValue(':amount', $amount);
            $insertStmt->execute();
            continue;
        }

        $carryMeter = $baseMeterStart;
        foreach ($invoiceRows as $invoice) {
            $insertStmt->bindValue(':tenant_id', $tenantId);
            $insertStmt->bindValue(':reservation_id', $reservationId);
            $insertStmt->bindValue(':invoice_id', intval($invoice['id']));
            $insertStmt->bindValue(':fee_type', $feeType);
            $insertStmt->bindValue(':charge_mode', 'per_cycle');
            $insertStmt->bindValue(':description', $description);
            $insertStmt->bindValue(':meter_start', $carryMeter);
            $insertStmt->bindValue(':meter_end', $carryMeter);
            $insertStmt->bindValue(':period_start', $invoice['periodStart']);
            $insertStmt->bindValue(':period_end', $invoice['periodEnd']);
            $insertStmt->bindValue(':amount', 0);
            $insertStmt->execute();
        }
    }
}

function applyInvoiceServiceFeePayloads($db, $tenantId, $reservationId, $payloads)
{
    if (!is_array($payloads) || empty($payloads)) {
        return;
    }

    $invoiceCycles = [];
    $invoiceStmt = $db->prepare("SELECT id, cycle_index FROM reservation_invoices WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id ORDER BY cycle_index ASC, id ASC");
    $invoiceStmt->bindValue(':tenant_id', $tenantId);
    $invoiceStmt->bindValue(':reservation_id', $reservationId);
    $invoiceStmt->execute();
    foreach ($invoiceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $invoiceCycles[intval($row['id'])] = intval($row['cycle_index']);
    }
    if (empty($invoiceCycles)) {
        return;
    }

    $byInvoice = [];
    foreach ($payloads as $item) {
        if (!is_object($item) || !isset($item->invoiceId)) {
            continue;
        }
        $invoiceId = intval($item->invoiceId);
        if (!isset($invoiceCycles[$invoiceId])) {
            continue;
        }
        $byInvoice[$invoiceId] = $item;
    }

    $reservationInfoStmt = $db->prepare("SELECT start, `end` FROM reservations WHERE id = :id AND tenant_id = :tenant_id");
    $reservationInfoStmt->bindValue(':id', $reservationId);
    $reservationInfoStmt->bindValue(':tenant_id', $tenantId);
    $reservationInfoStmt->execute();
    $reservationInfo = $reservationInfoStmt->fetch(PDO::FETCH_ASSOC);
    $isMonthly = $reservationInfo ? isMonthlyCycleRange($reservationInfo['start'], $reservationInfo['end']) : false;
    $targetMode = $isMonthly ? 'per_cycle' : 'one_time';

    $deletePerCycleStmt = $db->prepare("DELETE FROM reservation_service_fees
                                        WHERE tenant_id = :tenant_id
                                          AND reservation_id = :reservation_id
                                          AND invoice_id = :invoice_id
                                          AND charge_mode = :charge_mode");
    $insertFeeStmt = $db->prepare("INSERT INTO reservation_service_fees (tenant_id, reservation_id, invoice_id, fee_type, charge_mode, description, meter_start, meter_end, period_start, period_end, amount)
                                   VALUES (:tenant_id, :reservation_id, :invoice_id, :fee_type, :charge_mode, :description, :meter_start, :meter_end, :period_start, :period_end, :amount)");
    $periodStmt = $db->prepare("SELECT period_start, period_end FROM reservation_invoices WHERE id = :invoice_id AND tenant_id = :tenant_id AND reservation_id = :reservation_id");

    foreach ($byInvoice as $invoiceId => $item) {
        $deletePerCycleStmt->bindValue(':tenant_id', $tenantId);
        $deletePerCycleStmt->bindValue(':reservation_id', $reservationId);
        $deletePerCycleStmt->bindValue(':invoice_id', $invoiceId);
        $deletePerCycleStmt->bindValue(':charge_mode', $targetMode);
        $deletePerCycleStmt->execute();

        $periodStmt->bindValue(':invoice_id', $invoiceId);
        $periodStmt->bindValue(':tenant_id', $tenantId);
        $periodStmt->bindValue(':reservation_id', $reservationId);
        $periodStmt->execute();
        $periodRow = $periodStmt->fetch(PDO::FETCH_ASSOC);
        $periodStart = $periodRow ? $periodRow['period_start'] : null;
        $periodEnd = $periodRow ? $periodRow['period_end'] : null;

        $fees = [];
        if (isset($item->fees) && is_array($item->fees)) {
            foreach ($item->fees as $feeItem) {
                if (!is_object($feeItem)) {
                    continue;
                }
                $feeType = isset($feeItem->feeType) ? trim((string)$feeItem->feeType) : '';
                if (!in_array($feeType, ['electricity', 'water', 'other'], true)) {
                    continue;
                }
                $fees[] = [
                    'feeType' => $feeType,
                    'amount' => isset($feeItem->amount) ? floatval($feeItem->amount) : 0,
                    'description' => isset($feeItem->description) ? trim((string)$feeItem->description) : '',
                    'meterStart' => isset($feeItem->meterStart) ? floatval($feeItem->meterStart) : 0,
                    'meterEnd' => isset($feeItem->meterEnd) ? floatval($feeItem->meterEnd) : 0
                ];
            }
        }
        $hasElectric = false;
        $hasWater = false;
        foreach ($fees as $fee) {
            if ($fee['feeType'] === 'electricity') {
                $hasElectric = true;
            }
            if ($fee['feeType'] === 'water') {
                $hasWater = true;
            }
            $insertFeeStmt->bindValue(':tenant_id', $tenantId);
            $insertFeeStmt->bindValue(':reservation_id', $reservationId);
            $insertFeeStmt->bindValue(':invoice_id', $invoiceId);
            $insertFeeStmt->bindValue(':fee_type', $fee['feeType']);
            $insertFeeStmt->bindValue(':charge_mode', $targetMode);
            $insertFeeStmt->bindValue(':description', $fee['description']);
            $insertFeeStmt->bindValue(':meter_start', max(0, $fee['meterStart']));
            $insertFeeStmt->bindValue(':meter_end', max(0, $fee['meterEnd']));
            $insertFeeStmt->bindValue(':period_start', $periodStart);
            $insertFeeStmt->bindValue(':period_end', $periodEnd);
            $insertFeeStmt->bindValue(':amount', max(0, $fee['amount']));
            $insertFeeStmt->execute();
        }
        if ($isMonthly && !$hasElectric) {
            $insertFeeStmt->bindValue(':tenant_id', $tenantId);
            $insertFeeStmt->bindValue(':reservation_id', $reservationId);
            $insertFeeStmt->bindValue(':invoice_id', $invoiceId);
            $insertFeeStmt->bindValue(':fee_type', 'electricity');
            $insertFeeStmt->bindValue(':charge_mode', $targetMode);
            $insertFeeStmt->bindValue(':description', '');
            $insertFeeStmt->bindValue(':meter_start', 0);
            $insertFeeStmt->bindValue(':meter_end', 0);
            $insertFeeStmt->bindValue(':period_start', $periodStart);
            $insertFeeStmt->bindValue(':period_end', $periodEnd);
            $insertFeeStmt->bindValue(':amount', 0);
            $insertFeeStmt->execute();
        }
        if ($isMonthly && !$hasWater) {
            $insertFeeStmt->bindValue(':tenant_id', $tenantId);
            $insertFeeStmt->bindValue(':reservation_id', $reservationId);
            $insertFeeStmt->bindValue(':invoice_id', $invoiceId);
            $insertFeeStmt->bindValue(':fee_type', 'water');
            $insertFeeStmt->bindValue(':charge_mode', $targetMode);
            $insertFeeStmt->bindValue(':description', '');
            $insertFeeStmt->bindValue(':meter_start', 0);
            $insertFeeStmt->bindValue(':meter_end', 0);
            $insertFeeStmt->bindValue(':period_start', $periodStart);
            $insertFeeStmt->bindValue(':period_end', $periodEnd);
            $insertFeeStmt->bindValue(':amount', 0);
            $insertFeeStmt->execute();
        }
    }

    $syncMeterType = function ($feeType, $endField) use ($db, $tenantId, $reservationId, $byInvoice, $invoiceCycles) {
        $rowsStmt = $db->prepare("SELECT f.id, f.invoice_id, f.meter_start, f.meter_end
                                  FROM reservation_service_fees f
                                  INNER JOIN reservation_invoices i ON i.id = f.invoice_id
                                  WHERE f.tenant_id = :tenant_id
                                    AND f.reservation_id = :reservation_id
                                    AND f.fee_type = :fee_type
                                    AND f.charge_mode = 'per_cycle'
                                  ORDER BY i.cycle_index ASC, f.id ASC");
        $rowsStmt->bindValue(':tenant_id', $tenantId);
        $rowsStmt->bindValue(':reservation_id', $reservationId);
        $rowsStmt->bindValue(':fee_type', $feeType);
        $rowsStmt->execute();
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return;
        }
        $carry = max(0, floatval($rows[0]['meter_start']));
        $updateMeterStmt = $db->prepare("UPDATE reservation_service_fees SET meter_start = :meter_start, meter_end = :meter_end WHERE id = :id");
        foreach ($rows as $row) {
            $invoiceId = intval($row['invoice_id']);
            $payload = isset($byInvoice[$invoiceId]) ? $byInvoice[$invoiceId] : null;
            $nextEnd = $payload && isset($payload->{$endField}) ? floatval($payload->{$endField}) : floatval($row['meter_end']);
            if ($nextEnd < $carry) {
                $nextEnd = $carry;
            }
            $updateMeterStmt->bindValue(':meter_start', $carry);
            $updateMeterStmt->bindValue(':meter_end', $nextEnd);
            $updateMeterStmt->bindValue(':id', intval($row['id']));
            $updateMeterStmt->execute();
            $carry = $nextEnd;
        }
    };

    $syncMeterType('electricity', 'electricMeterEnd');
    $syncMeterType('water', 'waterMeterEnd');
}

function recomputeReservationInvoicesFromServiceFees($db, $tenantId, $reservationId)
{
    $invoiceStmt = $db->prepare("SELECT id, room_amount, payment_status FROM reservation_invoices WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $invoiceStmt->bindValue(':tenant_id', $tenantId);
    $invoiceStmt->bindValue(':reservation_id', $reservationId);
    $invoiceStmt->execute();
    $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($invoices)) {
        return;
    }

    $sumStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS service_sum FROM reservation_service_fees WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id AND invoice_id = :invoice_id");
    $updateStmt = $db->prepare("UPDATE reservation_invoices
                                SET service_amount = :service_amount,
                                    total_amount = :total_amount,
                                    paid_amount = :paid_amount
                                WHERE id = :id AND tenant_id = :tenant_id");
    $reservationTotal = 0;
    foreach ($invoices as $invoice) {
        $invoiceId = intval($invoice['id']);
        $roomAmount = floatval($invoice['room_amount']);
        $sumStmt->bindValue(':tenant_id', $tenantId);
        $sumStmt->bindValue(':reservation_id', $reservationId);
        $sumStmt->bindValue(':invoice_id', $invoiceId);
        $sumStmt->execute();
        $serviceAmount = floatval($sumStmt->fetchColumn());
        $totalAmount = round($roomAmount + $serviceAmount, 2);
        $paidAmount = ($invoice['payment_status'] === 'paid') ? $totalAmount : 0;

        $updateStmt->bindValue(':service_amount', $serviceAmount);
        $updateStmt->bindValue(':total_amount', $totalAmount);
        $updateStmt->bindValue(':paid_amount', $paidAmount);
        $updateStmt->bindValue(':id', $invoiceId);
        $updateStmt->bindValue(':tenant_id', $tenantId);
        $updateStmt->execute();
        $reservationTotal += $totalAmount;
    }

    $resStmt = $db->prepare("UPDATE reservations SET final_price = :final_price WHERE id = :id AND tenant_id = :tenant_id");
    $resStmt->bindValue(':final_price', round($reservationTotal, 2));
    $resStmt->bindValue(':id', $reservationId);
    $resStmt->bindValue(':tenant_id', $tenantId);
    $resStmt->execute();
}

function fetchInvoiceServiceFeesByReservation($db, $tenantId, $reservationId)
{
    $stmt = $db->prepare("SELECT invoice_id, fee_type, charge_mode, description, meter_start, meter_end, amount
                          FROM reservation_service_fees
                          WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id AND invoice_id IS NOT NULL");
    $stmt->bindValue(':tenant_id', $tenantId);
    $stmt->bindValue(':reservation_id', $reservationId);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $invoiceId = intval($row['invoice_id']);
        if (!isset($map[$invoiceId])) {
            $map[$invoiceId] = [];
        }
        $item = new stdClass();
        $item->feeType = $row['fee_type'];
        $item->chargeMode = isset($row['charge_mode']) ? $row['charge_mode'] : 'one_time';
        $item->description = isset($row['description']) ? $row['description'] : '';
        $item->meterStart = floatval($row['meter_start']);
        $item->meterEnd = floatval($row['meter_end']);
        $item->amount = floatval($row['amount']);
        $map[$invoiceId][] = $item;
    }
    return $map;
}

function applyReservationInvoicePayments($db, $tenantId, $reservationId, $invoicePayloads)
{
    if (!is_array($invoicePayloads) || empty($invoicePayloads)) {
        return;
    }

    $validMethods = ['cash', 'bank_transfer', 'card_international', 'card_domestic_atm'];
    $invoiceStmt = $db->prepare("SELECT id, total_amount FROM reservation_invoices WHERE id = :id AND tenant_id = :tenant_id AND reservation_id = :reservation_id");
    $updateStmt = $db->prepare("UPDATE reservation_invoices
                                SET payment_status = :payment_status,
                                    payment_method = :payment_method,
                                    paid_amount = :paid_amount,
                                    paid_at = :paid_at,
                                    payment_ref = :payment_ref,
                                    payment_note = :payment_note
                                WHERE id = :id AND tenant_id = :tenant_id AND reservation_id = :reservation_id");

    foreach ($invoicePayloads as $item) {
        if (!is_object($item) || !isset($item->id)) {
            continue;
        }
        $invoiceId = intval($item->id);
        if ($invoiceId <= 0) {
            continue;
        }
        $invoiceStmt->bindValue(':id', $invoiceId);
        $invoiceStmt->bindValue(':tenant_id', $tenantId);
        $invoiceStmt->bindValue(':reservation_id', $reservationId);
        $invoiceStmt->execute();
        $invoiceRow = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoiceRow) {
            continue;
        }

        $paymentStatus = isset($item->paymentStatus) && $item->paymentStatus === 'paid' ? 'paid' : 'unpaid';
        $paymentMethod = isset($item->paymentMethod) ? trim((string)$item->paymentMethod) : null;
        if (!in_array($paymentMethod, $validMethods, true)) {
            $paymentMethod = null;
        }
        $paidAt = isset($item->paidAt) ? trim((string)$item->paidAt) : null;
        $paymentRef = isset($item->paymentRef) ? trim((string)$item->paymentRef) : null;
        $paymentNote = isset($item->paymentNote) ? trim((string)$item->paymentNote) : null;
        $paidAmount = $paymentStatus === 'paid' ? floatval($invoiceRow['total_amount']) : 0;

        if ($paymentStatus === 'paid') {
            if (!$paymentMethod) {
                $paymentMethod = 'cash';
            }
            if (!$paidAt) {
                $paidAt = (new DateTime())->format('Y-m-d H:i:s');
            }
        } else {
            $paymentMethod = null;
            $paidAt = null;
            $paymentRef = null;
            $paymentNote = null;
        }

        $updateStmt->bindValue(':payment_status', $paymentStatus);
        $updateStmt->bindValue(':payment_method', $paymentMethod);
        $updateStmt->bindValue(':paid_amount', $paidAmount);
        $updateStmt->bindValue(':paid_at', $paidAt);
        $updateStmt->bindValue(':payment_ref', $paymentRef);
        $updateStmt->bindValue(':payment_note', $paymentNote);
        $updateStmt->bindValue(':id', $invoiceId);
        $updateStmt->bindValue(':tenant_id', $tenantId);
        $updateStmt->bindValue(':reservation_id', $reservationId);
        $updateStmt->execute();
    }
}

function fetchReservationInvoices($db, $tenantId, $reservationId)
{
    $stmt = $db->prepare("SELECT id, cycle_index, period_start, period_end, occupied_days, month_days, room_amount, service_amount, total_amount, payment_status, payment_method, paid_amount, paid_at, payment_ref, payment_note
                          FROM reservation_invoices
                          WHERE tenant_id = :tenant_id AND reservation_id = :reservation_id
                          ORDER BY cycle_index ASC, id ASC");
    $stmt->bindValue(':tenant_id', $tenantId);
    $stmt->bindValue(':reservation_id', $reservationId);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id' => intval($row['id']),
            'cycleIndex' => intval($row['cycle_index']),
            'periodStart' => $row['period_start'],
            'periodEnd' => $row['period_end'],
            'occupiedDays' => intval($row['occupied_days']),
            'monthDays' => intval($row['month_days']),
            'roomAmount' => floatval($row['room_amount']),
            'serviceAmount' => floatval($row['service_amount']),
            'totalAmount' => floatval($row['total_amount']),
            'paymentStatus' => $row['payment_status'],
            'paymentMethod' => $row['payment_method'],
            'paidAmount' => floatval($row['paid_amount']),
            'paidAt' => $row['paid_at'],
            'paymentRef' => $row['payment_ref'],
            'paymentNote' => $row['payment_note']
        ];
    }
    return $result;
}
