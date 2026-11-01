<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$raw_body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$secret = lms_config('razorpay.webhook_secret', '');

if (empty($secret) || empty($signature) || empty($raw_body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing signature or payload']);
    exit;
}

// 1. Verify HMAC against raw request body
$expected = hash_hmac('sha256', $raw_body, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

$data = json_decode($raw_body, true);
$event = $data['event'] ?? '';

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    if ($event === 'order.paid' || $event === 'payment.captured') {
        $payment_entity = $data['payload']['payment']['entity'] ?? [];
        $order_id = $payment_entity['order_id'] ?? '';
        $payment_id = $payment_entity['id'] ?? '';
        $captured_amount_paise = (int)($payment_entity['amount'] ?? 0);
        $captured_currency = strtoupper(trim($payment_entity['currency'] ?? ''));

        if (empty($order_id)) {
            echo json_encode(['status' => 'ignored', 'reason' => 'missing_order_id']);
            exit;
        }

        // 2. Lookup payment order
        $stmt = $pdo->prepare("SELECT p.*, prog.price_inr, prog.title as program_title 
            FROM payments p 
            JOIN programs prog ON p.program_id = prog.id 
            WHERE p.razorpay_order_id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $payment_row = $stmt->fetch();

        // If event is for an unknown order, ignore cleanly with HTTP 200 to halt retries
        if (!$payment_row) {
            echo json_encode(['status' => 'ignored', 'reason' => 'unknown_order']);
            exit;
        }

        // 3. Idempotent check: if already processed, return success without duplicate work
        if ($payment_row['status'] === 'paid') {
            echo json_encode(['status' => 'ok', 'already_processed' => true]);
            exit;
        }

        // 4. Validate captured amount and currency against stored payment and program price
        $expected_paise = (int)$payment_row['amount_inr'] * 100;
        $program_paise = (int)$payment_row['price_inr'] * 100;

        if ($captured_amount_paise < $expected_paise || $captured_amount_paise < $program_paise || $captured_currency !== 'INR') {
            lms_audit_log($payment_row['user_id'], 'razorpay_webhook_amount_mismatch', 'payments', $payment_row['id'], [
                'order_id' => $order_id,
                'captured_paise' => $captured_amount_paise,
                'expected_paise' => $expected_paise,
                'currency' => $captured_currency
            ]);
            http_response_code(400);
            echo json_encode(['error' => 'Payment amount or currency mismatch']);
            exit;
        }

        // 5. Update payment status to 'paid'
        $upd = $pdo->prepare("UPDATE payments SET status = 'paid', razorpay_payment_id = ? WHERE id = ?");
        $upd->execute([$payment_id, $payment_row['id']]);

        // 6. Grant or activate enrollment (180 days default)
        $expires_at = date('Y-m-d H:i:s', time() + (180 * 86400));
        $enroll_stmt = $pdo->prepare("INSERT INTO enrollments (user_id, program_id, status, source, payment_id, starts_at, expires_at) 
            VALUES (?, ?, 'active', 'razorpay', ?, NOW(), ?) 
            ON DUPLICATE KEY UPDATE status = 'active', payment_id = VALUES(payment_id), expires_at = VALUES(expires_at)");
        $enroll_stmt->execute([$payment_row['user_id'], $payment_row['program_id'], $payment_id, $expires_at]);

        lms_audit_log($payment_row['user_id'], 'razorpay_webhook_paid', 'enrollments', null, [
            'order_id' => $order_id,
            'payment_id' => $payment_id,
            'program' => $payment_row['program_title']
        ]);
    }

    echo json_encode(['status' => 'ok']);
    exit;

} catch (Exception $e) {
    error_log("Razorpay webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error processing payment webhook.']);
    exit;
}
