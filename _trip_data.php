<?php

function normalizeTripDiscountType($value)
{
    $type = is_string($value) ? strtolower(trim($value)) : '';
    return $type === 'percent' ? 'percent' : 'fixed';
}

function sanitizeTripDiscountValue($value, $discountType)
{
    $number = floatval($value);
    if ($number < 0) {
        $number = 0;
    }
    if ($discountType === 'percent' && $number > 100) {
        $number = 100;
    }
    return $number;
}

function computeTripFinalPrice($tripPrice, $discountType, $discountValue)
{
    $price = floatval($tripPrice);
    if ($price < 0) {
        $price = 0;
    }
    if ($discountType === 'percent') {
        $final = $price - ($price * floatval($discountValue) / 100);
    } else {
        $final = $price - floatval($discountValue);
    }
    return $final < 0 ? 0 : $final;
}

function normalizeTripPaymentStatus($value)
{
    $text = is_string($value) ? strtolower(trim($value)) : '';
    if ($text === 'partial' || $text === 'paid') {
        return $text;
    }
    return 'unpaid';
}

function normalizeTripStatus($value)
{
    $text = is_string($value) ? strtolower(trim($value)) : '';
    if ($text === 'confirmed') {
        return 'Confirmed';
    }
    if ($text === 'intrip' || $text === 'in_trip') {
        return 'InTrip';
    }
    if ($text === 'done') {
        return 'Done';
    }
    if ($text === 'cancelled' || $text === 'canceled') {
        return 'Cancelled';
    }
    return 'New';
}

function extractTripCostsPayload($params)
{
    $payload = (is_object($params) && isset($params->tripCosts) && is_array($params->tripCosts)) ? $params->tripCosts : [];
    $allowedTypes = ['driver', 'toll', 'parking', 'other'];
    $result = [];
    foreach ($payload as $row) {
        if (!is_object($row) && !is_array($row)) {
            continue;
        }
        $source = (object)$row;
        $type = isset($source->costType) ? strtolower(trim((string)$source->costType)) : 'other';
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'other';
        }
        $amount = isset($source->amount) ? floatval($source->amount) : 0;
        if ($amount < 0) {
            $amount = 0;
        }
        $description = isset($source->description) ? trim((string)$source->description) : '';
        $incurredAt = isset($source->incurredAt) ? trim((string)$source->incurredAt) : null;
        if ($incurredAt === '') {
            $incurredAt = null;
        }
        if ($amount <= 0 && $description === '') {
            continue;
        }
        $item = new stdClass();
        $item->costType = $type;
        $item->amount = $amount;
        $item->description = $description;
        $item->incurredAt = $incurredAt;
        $result[] = $item;
    }
    return $result;
}

function computeTripAmountsByDistance($distanceKm, $pricePerKm, $fuelLitersPer100Km, $fuelPricePerLiter)
{
    $distance = max(0, floatval($distanceKm));
    $priceKm = max(0, floatval($pricePerKm));
    $litersPer100 = max(0, floatval($fuelLitersPer100Km));
    $fuelPrice = max(0, floatval($fuelPricePerLiter));

    $distanceAmount = $distance * $priceKm;
    $fuelEstimated = ($distance * $litersPer100 / 100) * $fuelPrice;
    $tripPrice = round($distanceAmount + $fuelEstimated, 2);

    return [
        'distance_km' => $distance,
        'distance_amount' => round($distanceAmount, 2),
        'fuel_estimated_cost' => round($fuelEstimated, 2),
        'trip_price' => $tripPrice
    ];
}

function computeTripFinalTotal($tripPrice, $otherCostsTotal, $discountType, $discountValue)
{
    $subtotal = max(0, floatval($tripPrice)) + max(0, floatval($otherCostsTotal));
    $type = normalizeTripDiscountType($discountType);
    $discount = sanitizeTripDiscountValue($discountValue, $type);
    if ($type === 'percent') {
        $final = $subtotal - ($subtotal * $discount / 100);
    } else {
        $final = $subtotal - $discount;
    }
    return round(max(0, $final), 2);
}

function calculateTripCostsTotal($tripCosts)
{
    $total = 0;
    if (!is_array($tripCosts)) {
        return $total;
    }
    foreach ($tripCosts as $item) {
        if (is_object($item) && isset($item->amount)) {
            $total += floatval($item->amount);
        } elseif (is_array($item) && isset($item['amount'])) {
            $total += floatval($item['amount']);
        }
    }
    return $total;
}

