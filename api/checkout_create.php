<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user) {
    json_res(['error' => 'Please sign in to enroll in programs.'], 401);
}

require_csrf();

$program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
if ($program_id <= 0) {
    json_res(['error' => 'Invalid program selected.'], 400);
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database error.'], 500);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$program_id]);
    $program = $stmt->fetch();

    if (!$program) {
        json_res(['error' => 'Program not found or currently inactive.'], 404);
    }

    $amount_inr = (int)$program['price_inr'];
    $amount_paise = $amount_inr * 100;

    $razorpay_enabled = (bool)lms_config('razorpay.enabled', false);
    $key_id = lms_config('razorpay.key_id', '');
    $key_secret = lms_config('razorpay.key_secret', '');

    $order_id = 'order_' . bin2hex(random_bytes(10));

    if ($razorpay_enabled && !empty($key_id) && !empty($key_secret) && $key_id !== 'rzp_live_placeholder') {
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$key_id}:{$key_secret}",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amount_paise,
                'currency' => 'INR',
                'receipt' => "rcpt_u{$user['id']}_p{$program_id}_" . time(),
                'notes' => [
                    'user_id' => $user['id'],
                    'program_id' => $program_id
                ]
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $order_data = json_decode($res, true);
            if (!empty($order_data['id'])) {
                $order_id = $order_data['id'];
            }
        }
    }

    // Insert pending payment record
    $ins = $pdo->prepare("INSERT INTO payments (user_id, program_id, razorpay_order_id, amount_inr, status, created_at) VALUES (?, ?, ?, ?, 'created', NOW())");
    $ins->execute([$user['id'], $program_id, $order_id, $amount_inr]);
    $payment_record_id = (int)$pdo->lastInsertId();

    json_res([
        'success' => true,
        'order_id' => $order_id,
        'payment_id' => $payment_record_id,
        'key_id' => $key_id,
        'amount_inr' => $amount_inr,
        'amount_paise' => $amount_paise,
        'currency' => 'INR',
        'program_title' => $program['title'],
        'user' => [
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Checkout error: " . $e->getMessage());
    json_res(['error' => 'Internal server error initializing checkout.'], 500);
}
