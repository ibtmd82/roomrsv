<?php
require_once '_db.php';

$json = file_get_contents('php://input');
$params = json_decode($json);
$tenantContext = resolveTenantContext();
$tenantId = $tenantContext['tenant_id'];

$invoiceId = isset($params->invoiceId) ? intval($params->invoiceId) : 0;
$paymentStatus = isset($params->paymentStatus) ? trim((string)$params->paymentStatus) : 'unpaid';
$paymentMethod = isset($params->paymentMethod) ? trim((string)$params->paymentMethod) : null;
$paidAt = isset($params->paidAt) ? trim((string)$params->paidAt) : null;
$paymentRef = isset($params->paymentRef) ? trim((string)$params->paymentRef) : null;
$paymentNote = isset($params->paymentNote) ? trim((string)$params->paymentNote) : null;

if (!in_array($paymentStatus, ['unpaid', 'paid'], true)) {
    $paymentStatus = 'unpaid';
}

if ($paymentStatus === 'paid' && !in_array($paymentMethod, ['cash', 'bank_transfer', 'card_international', 'card_domestic_atm'], true)) {
    $paymentMethod = 'cash';
}
if ($paymentStatus === 'unpaid') {
    $paymentMethod = null;
}

$invoiceStmt = $db->prepare("SELECT id, reservation_id, total_amount FROM reservation_invoices WHERE id = :id AND tenant_id = :tenant_id");
$invoiceStmt->bindValue(':id', $invoiceId);
$invoiceStmt->bindValue(':tenant_id', $tenantId);
$invoiceStmt->execute();
$invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['result' => 'Error', 'message' => 'Invoice not found']);
    exit;
}

$paidAmount = $paymentStatus === 'paid' ? floatval($invoice['total_amount']) : 0;
if ($paymentStatus === 'paid') {
    if (!$paidAt) {
        $paidAt = (new DateTime())->format('Y-m-d H:i:s');
    }
} else {
    $paidAt = null;
    $paymentRef = null;
    $paymentNote = null;
}

$updateStmt = $db->prepare("UPDATE reservation_invoices
                            SET payment_status = :payment_status,
                                payment_method = :payment_method,
                                paid_amount = :paid_amount,
                                paid_at = :paid_at,
                                payment_ref = :payment_ref,
                                payment_note = :payment_note
                            WHERE id = :id AND tenant_id = :tenant_id");
$updateStmt->bindValue(':payment_status', $paymentStatus);
$updateStmt->bindValue(':payment_method', $paymentMethod);
$updateStmt->bindValue(':paid_amount', $paidAmount);
$updateStmt->bindValue(':paid_at', $paidAt);
$updateStmt->bindValue(':payment_ref', $paymentRef);
$updateStmt->bindValue(':payment_note', $paymentNote);
$updateStmt->bindValue(':id', $invoiceId);
$updateStmt->bindValue(':tenant_id', $tenantId);
$updateStmt->execute();

$result = new stdClass();
$result->result = 'OK';
$result->invoiceId = $invoiceId;
$result->reservationId = intval($invoice['reservation_id']);
$result->paymentStatus = $paymentStatus;
$result->paymentMethod = $paymentMethod;
$result->paidAmount = $paidAmount;
$result->paidAt = $paidAt;

header('Content-Type: application/json');
echo json_encode($result);
