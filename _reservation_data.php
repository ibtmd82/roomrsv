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

    $insertStmt = $db->prepare("INSERT INTO reservation_service_fees (tenant_id, reservation_id, fee_type, description, meter_start, meter_end, period_start, period_end, amount) VALUES (:tenant_id, :reservation_id, :fee_type, :description, :meter_start, :meter_end, :period_start, :period_end, :amount)");
    foreach ($fees as $fee) {
        $insertStmt->bindValue(':tenant_id', $tenantId);
        $insertStmt->bindValue(':reservation_id', $reservationId);
        $insertStmt->bindValue(':fee_type', $fee['fee_type']);
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
