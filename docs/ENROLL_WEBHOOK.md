# VLSI LMS : Enrollment Webhook Integration Guide

This specification describes how the main website (`vlsiinsights.com`) or external billing systems securely provision student enrollments in the LMS (`learn.vlsiinsights.com`).

---

## 1. Webhook Endpoint Details

- **Target URL**: `https://learn.vlsiinsights.com/api/enroll_webhook.php`
- **Method**: `POST`
- **Content-Type**: `application/json; charset=utf-8`

### Required HTTP Headers
| Header Name | Type | Description |
|---|---|---|
| `X-Timestamp` | Integer | Unix epoch timestamp in seconds (e.g. `1761998400`). Server rejects requests with skew > 300s (5 minutes). |
| `X-Signature` | Hex String | Lowercase 64-character HMAC-SHA256 signature computed over `timestamp . "." . raw_body`. |

---

## 2. Cryptographic Signature Calculation

1. Prepare the JSON request body as a raw string.
2. Obtain current UNIX epoch timestamp in seconds.
3. Concatenate the string: `${timestamp}.${raw_json_body}`
4. Calculate HMAC-SHA256 using the shared secret configured in `enrollment.webhook_secret`:
   `hash_hmac('sha256', "${timestamp}.${raw_json_body}", $secret)`

---

## 3. JSON Request Payload

```json
{
  "user_email": "student@example.com",
  "program_slug": "physical-design",
  "source": "website_purchase",
  "duration_days": 180
}
```

### Parameters
- `user_email` *(string, required)*: Student email address.
- `program_slug` *(string, required)*: Program slug matching the `programs.slug` database column (e.g. `physical-design`, `design-verification`, `sta-signoff`).
- `source` *(string, optional)*: Enrollment origin identifier (default: `import`).
- `duration_days` *(integer, optional)*: Access duration in days from grant time (default: `180`).

---

## 4. Response Codes and Format

### Successful Response (HTTP 200)
Initial enrollment grant:
```json
{
  "success": true,
  "already_enrolled": false,
  "user_id": 42,
  "program_id": 1,
  "expires_at": "2027-04-30 18:30:00"
}
```

Idempotent replay (subsequent request with same email and program):
```json
{
  "success": true,
  "already_enrolled": true,
  "user_id": 42,
  "program_id": 1,
  "expires_at": "2027-04-30 18:30:00"
}
```

### Error Responses
- **HTTP 401 Unauthorized**: Missing timestamp, timestamp skew > 300 seconds, missing signature, or cryptographic signature mismatch.
- **HTTP 400 Bad Request**: Malformed JSON, or missing required fields (`user_email`, `program_slug`).
- **HTTP 404 Not Found**: Unknown `program_slug`.
- **HTTP 500 Internal Server Error**: Database connection error or missing server webhook secret.

---

## 5. Implementation Examples

### PHP Implementation (Sender)
```php
<?php
function dispatchEnrollmentWebhook(string $email, string $programSlug, string $secret): array {
    $url = 'https://learn.vlsiinsights.com/api/enroll_webhook.php';
    $payload = json_encode([
        'user_email'    => $email,
        'program_slug'  => $programSlug,
        'source'        => 'website_purchase',
        'duration_days' => 180
    ], JSON_UNESCAPED_SLASHES);

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}
```

### Node.js Implementation (Sender)
```javascript
import crypto from 'node:crypto';

async function dispatchEnrollmentWebhook(email, programSlug, secret) {
  const url = 'https://learn.vlsiinsights.com/api/enroll_webhook.php';
  const body = JSON.stringify({
    user_email: email,
    program_slug: programSlug,
    source: 'website_purchase',
    duration_days: 180
  });

  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${body}`)
    .digest('hex');

  const res = await fetch(url, {
    method: 'POST',
    body: body,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'X-Timestamp': timestamp,
      'X-Signature': signature
    }
  });

  const data = await res.json();
  return { status: res.status, data };
}
```

---

## 6. Security Guarantees & Idempotency Testing
1. **Replay Protection**: The `X-Timestamp` header prevents old requests from being intercepted and re-sent later. Timestamps outside a +/- 300 second window are rejected with HTTP 401.
2. **Timing-Safe Verification**: The LMS verifies the signature using `hash_equals()`.
3. **Audit Trails**: Rejected webhook attempts are recorded in `audit_log` with IP and rejection reason, without logging the secret or payload body.
4. **Idempotent Upsert**: Multiple identical requests execute `INSERT INTO enrollments ... ON DUPLICATE KEY UPDATE` cleanly, guaranteeing that only one active enrollment row exists per user and program.
