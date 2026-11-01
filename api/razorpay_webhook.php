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

$expected = hash_hmac('sha256', $raw_body, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = json_decode($raw_body, true);
$event = $data['event'] ?? '';

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    exit;
}

try {
    if ($event === 'order.paid' || $event === 'payment.captured') {
        $payment_entity = $data['payload']['payment']['entity'] ?? [];
        $order_id = $payment_entity['order_id'] ?? '';
        $payment_id = $payment_entity['id'] ?? '';

        if (!empty($order_id)) {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE razorpay_order_id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $payment_row = $stmt->fetch();

            if ($payment_row) {
                // Update payment record
                $upd = $pdo->prepare("UPDATE payments SET status = 'paid', razorpay_payment_id = ? WHERE id = ?");
                $upd->execute([$payment_id, $payment_row['id']]);

                // Create or activate enrollment (180 days default)
                $expires_at = date('Y-m-d H:i:s', time() + (180 * 86400));
                $enroll_stmt = $pdo->prepare("INSERT INTO enrollments (user_id, program_id, status, source, payment_id, starts_at, expires_at) 
                    VALUES (?, ?, 'active', 'razorpay', ?, NOW(), ?) 
                    ON DUPLICATE KEY UPDATE status = 'active', payment_id = VALUES(payment_id), expires_at = VALUES(expires_at)");
                $enroll_stmt->execute([$payment_row['user_id'], $payment_row['program_id'], $payment_id, $expires_at]);

                lms_audit_log($payment_row['user_id'], 'razorpay_webhook_paid', 'enrollments', null, [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'program_id' => $payment_row['program_id']
                ]);
            }
        }
    }

    echo json_encode(['status' => 'ok']);
    exit;
} catch (Exception $e) {
    error_log("Razorpay webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
