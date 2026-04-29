<?php
require_once '_db.php';

$tenantContext = resolveTenantContext();
$tenantId = intval($tenantContext['tenant_id']);
$startDate = isset($_GET['startDate']) ? trim((string)$_GET['startDate']) : date('Y-m-d');
$endDate = isset($_GET['endDate']) ? trim((string)$_GET['endDate']) : $startDate;
$groupBy = isset($_GET['groupBy']) ? trim((string)$_GET['groupBy']) : 'day';
if ($groupBy !== 'month') {
    $groupBy = 'day';
}

$result = new stdClass();

$vehicleTotalStmt = $db->prepare("SELECT COUNT(*) AS count FROM vehicles WHERE tenant_id = :tenant_id");
$vehicleTotalStmt->bindValue(':tenant_id', $tenantId);
$vehicleTotalStmt->execute();
$result->vehiclesTotal = intval($vehicleTotalStmt->fetchColumn());

$tripTotalStmt = $db->prepare("SELECT COUNT(*) AS count FROM trip_reservations WHERE tenant_id = :tenant_id");
$tripTotalStmt->bindValue(':tenant_id', $tenantId);
$tripTotalStmt->execute();
$result->tripsTotal = intval($tripTotalStmt->fetchColumn());

$completedTripsStmt = $db->prepare("SELECT COUNT(*) FROM trip_reservations WHERE tenant_id = :tenant_id AND status = 'Done'");
$completedTripsStmt->bindValue(':tenant_id', $tenantId);
$completedTripsStmt->execute();
$result->completedTrips = intval($completedTripsStmt->fetchColumn());

$cancelledTripsStmt = $db->prepare("SELECT COUNT(*) FROM trip_reservations WHERE tenant_id = :tenant_id AND status = 'Cancelled'");
$cancelledTripsStmt->bindValue(':tenant_id', $tenantId);
$cancelledTripsStmt->execute();
$result->cancelledTrips = intval($cancelledTripsStmt->fetchColumn());

$result->completionRate = $result->tripsTotal > 0 ? round(($result->completedTrips / $result->tripsTotal) * 100, 2) : 0;
$result->cancellationRate = $result->tripsTotal > 0 ? round(($result->cancelledTrips / $result->tripsTotal) * 100, 2) : 0;

$revenueStmt = $db->prepare("SELECT COALESCE(SUM(final_price), 0) AS total, COALESCE(AVG(final_price), 0) AS average FROM trip_reservations WHERE tenant_id = :tenant_id");
$revenueStmt->bindValue(':tenant_id', $tenantId);
$revenueStmt->execute();
$revenueRow = $revenueStmt->fetch(PDO::FETCH_ASSOC);
$result->totalFinalPrice = floatval($revenueRow['total']);
$result->averageFinalPrice = floatval($revenueRow['average']);

$costStmt = $db->prepare("SELECT COALESCE(SUM(c.amount), 0) AS total
                          FROM trip_costs c
                          INNER JOIN trip_reservations t ON t.id = c.trip_reservation_id
                          WHERE c.tenant_id = :tenant_id AND t.tenant_id = :tenant_id");
$costStmt->bindValue(':tenant_id', $tenantId);
$costStmt->execute();
$totalCosts = floatval($costStmt->fetchColumn());
$result->totalCosts = $totalCosts;
$result->totalProfit = $result->totalFinalPrice - $totalCosts;
$result->profitMargin = $result->totalFinalPrice > 0 ? round(($result->totalProfit / $result->totalFinalPrice) * 100, 2) : 0;

$receivableStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_amount, COALESCE(SUM(paid_amount), 0) AS paid_amount FROM trip_invoices WHERE tenant_id = :tenant_id");
$receivableStmt->bindValue(':tenant_id', $tenantId);
$receivableStmt->execute();
$receivableRow = $receivableStmt->fetch(PDO::FETCH_ASSOC);
$result->totalReceivable = floatval($receivableRow['total_amount']);
$result->totalCollected = floatval($receivableRow['paid_amount']);
$result->totalOutstanding = max(0, $result->totalReceivable - $result->totalCollected);
$result->collectionRate = $result->totalReceivable > 0 ? round(($result->totalCollected / $result->totalReceivable) * 100, 2) : 0;

$statusStmt = $db->prepare("SELECT status, COUNT(*) AS count FROM trip_reservations WHERE tenant_id = :tenant_id GROUP BY status");
$statusStmt->bindValue(':tenant_id', $tenantId);
$statusStmt->execute();
$statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
$statusMap = new stdClass();
foreach ($statusRows as $row) {
    $statusMap->{$row['status']} = intval($row['count']);
}
$result->tripsByStatus = $statusMap;

$costTypeStmt = $db->prepare("SELECT cost_type, COALESCE(SUM(amount), 0) AS total FROM trip_costs WHERE tenant_id = :tenant_id GROUP BY cost_type");
$costTypeStmt->bindValue(':tenant_id', $tenantId);
$costTypeStmt->execute();
$costTypeRows = $costTypeStmt->fetchAll(PDO::FETCH_ASSOC);
$costTypeMap = new stdClass();
foreach ($costTypeRows as $row) {
    $costTypeMap->{$row['cost_type']} = floatval($row['total']);
}
$result->costsByType = $costTypeMap;

$latestStmt = $db->prepare("SELECT id, name, driver_name, start, `end`, status, final_price FROM trip_reservations WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 8");
$latestStmt->bindValue(':tenant_id', $tenantId);
$latestStmt->execute();
$latestRows = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
$latestTrips = [];
foreach ($latestRows as $row) {
    $item = new stdClass();
    $item->id = intval($row['id']);
    $item->name = $row['name'];
    $item->driverName = isset($row['driver_name']) ? $row['driver_name'] : '';
    $item->start = $row['start'];
    $item->end = $row['end'];
    $item->status = $row['status'];
    $item->finalPrice = floatval($row['final_price']);
    $latestTrips[] = $item;
}
$result->latestTrips = $latestTrips;

$lossStmt = $db->prepare("SELECT t.id, t.name, t.final_price, COALESCE(SUM(c.amount), 0) AS total_cost
                          FROM trip_reservations t
                          LEFT JOIN trip_costs c ON c.trip_reservation_id = t.id AND c.tenant_id = t.tenant_id
                          WHERE t.tenant_id = :tenant_id
                          GROUP BY t.id, t.name, t.final_price
                          HAVING COALESCE(SUM(c.amount), 0) > t.final_price
                          ORDER BY (COALESCE(SUM(c.amount), 0) - t.final_price) DESC
                          LIMIT 5");
$lossStmt->bindValue(':tenant_id', $tenantId);
$lossStmt->execute();
$lossRows = $lossStmt->fetchAll(PDO::FETCH_ASSOC);
$lossTrips = [];
foreach ($lossRows as $row) {
    $item = new stdClass();
    $item->id = intval($row['id']);
    $item->name = $row['name'];
    $item->finalPrice = floatval($row['final_price']);
    $item->totalCost = floatval($row['total_cost']);
    $item->lossAmount = $item->totalCost - $item->finalPrice;
    $lossTrips[] = $item;
}
$result->lossTrips = $lossTrips;
$result->lossTripsCount = count($lossTrips);

$utilizationStmt = $db->prepare("SELECT start, `end` FROM trip_reservations
                                 WHERE tenant_id = :tenant_id
                                 AND start IS NOT NULL
                                 AND `end` IS NOT NULL
                                 AND NOT ((`end` < :start_date) OR (start > :end_date))");
$utilizationStmt->bindValue(':tenant_id', $tenantId);
$utilizationStmt->bindValue(':start_date', $startDate . ' 00:00:00');
$utilizationStmt->bindValue(':end_date', $endDate . ' 23:59:59');
$utilizationStmt->execute();
$utilizationRows = $utilizationStmt->fetchAll(PDO::FETCH_ASSOC);
$bookedHours = 0.0;
$rangeStartTs = strtotime($startDate . ' 00:00:00');
$rangeEndTs = strtotime($endDate . ' 23:59:59');
foreach ($utilizationRows as $uRow) {
    $tripStart = strtotime($uRow['start']);
    $tripEnd = strtotime($uRow['end']);
    if ($tripStart === false || $tripEnd === false || $tripEnd <= $tripStart) {
        continue;
    }
    $effectiveStart = max($tripStart, $rangeStartTs);
    $effectiveEnd = min($tripEnd, $rangeEndTs);
    if ($effectiveEnd > $effectiveStart) {
        $bookedHours += ($effectiveEnd - $effectiveStart) / 3600;
    }
}
$result->bookedHours = round($bookedHours, 2);
$periodHours = max(1.0, ($rangeEndTs - $rangeStartTs) / 3600);
$capacityHours = max(1.0, $periodHours * max(1, $result->vehiclesTotal));
$result->utilizationRate = round(min(100, ($bookedHours / $capacityHours) * 100), 2);

$topVehiclesStmt = $db->prepare("SELECT v.name AS vehicle_name, COUNT(t.id) AS trip_count, COALESCE(SUM(t.final_price), 0) AS revenue
                                 FROM trip_reservations t
                                 LEFT JOIN vehicles v ON v.id = t.vehicle_id AND v.tenant_id = t.tenant_id
                                 WHERE t.tenant_id = :tenant_id
                                 GROUP BY t.vehicle_id, v.name
                                 ORDER BY revenue DESC
                                 LIMIT 5");
$topVehiclesStmt->bindValue(':tenant_id', $tenantId);
$topVehiclesStmt->execute();
$topVehiclesRows = $topVehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
$topVehicles = [];
foreach ($topVehiclesRows as $row) {
    $item = new stdClass();
    $item->vehicleName = $row['vehicle_name'] ?: 'Chua dat ten';
    $item->tripCount = intval($row['trip_count']);
    $item->revenue = floatval($row['revenue']);
    $topVehicles[] = $item;
}
$result->topVehicles = $topVehicles;

$topDriversStmt = $db->prepare("SELECT TRIM(COALESCE(driver_name, '')) AS driver_name, COUNT(*) AS trip_count, COALESCE(SUM(final_price), 0) AS revenue
                                FROM trip_reservations
                                WHERE tenant_id = :tenant_id
                                GROUP BY TRIM(COALESCE(driver_name, ''))
                                ORDER BY revenue DESC
                                LIMIT 5");
$topDriversStmt->bindValue(':tenant_id', $tenantId);
$topDriversStmt->execute();
$topDriversRows = $topDriversStmt->fetchAll(PDO::FETCH_ASSOC);
$topDrivers = [];
foreach ($topDriversRows as $row) {
    $item = new stdClass();
    $item->driverName = $row['driver_name'] !== '' ? $row['driver_name'] : 'Chua nhap tai xe';
    $item->tripCount = intval($row['trip_count']);
    $item->revenue = floatval($row['revenue']);
    $topDrivers[] = $item;
}
$result->topDrivers = $topDrivers;

$seriesStmt = $db->prepare("SELECT start, final_price FROM trip_reservations WHERE tenant_id = :tenant_id AND date(start) >= :start_date AND date(start) <= :end_date");
$seriesStmt->bindValue(':tenant_id', $tenantId);
$seriesStmt->bindValue(':start_date', $startDate);
$seriesStmt->bindValue(':end_date', $endDate);
$seriesStmt->execute();
$seriesRows = $seriesStmt->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($seriesRows as $row) {
    $key = $groupBy === 'month' ? date('Y-m', strtotime($row['start'])) : date('Y-m-d', strtotime($row['start']));
    if (!isset($grouped[$key])) {
        $grouped[$key] = 0;
    }
    $grouped[$key] += floatval($row['final_price']);
}
ksort($grouped);
$series = [];
foreach ($grouped as $period => $amount) {
    $item = new stdClass();
    $item->period = $period;
    $item->amount = round($amount, 2);
    $series[] = $item;
}
$result->revenueSeries = $series;
$result->revenueFilter = (object)[
    'startDate' => $startDate,
    'endDate' => $endDate,
    'groupBy' => $groupBy
];

header('Content-Type: application/json');
echo json_encode($result);

