<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    die("Not found.\n");
}
/**
 * CLI Test Harness for HTTP Range Parser (conforming to RFC 9110)
 */

function parseRangeHeader(string $range_header, int $filesize): array {
    if (str_contains($range_header, ',')) {
        return ['status' => 416, 'error' => 'multi_range_rejected'];
    }

    if (!preg_match('/^bytes=\s*(\d*)\s*-\s*(\d*)$/i', $range_header, $matches)) {
        return ['status' => 416, 'error' => 'malformed_header'];
    }

    $raw_start = $matches[1];
    $raw_end = $matches[2];

    if ($raw_start === '' && $raw_end === '') {
        return ['status' => 416, 'error' => 'empty_range'];
    }

    if ($raw_start === '') {
        // Suffix range (e.g. bytes=-500)
        $suffix_len = (int)$raw_end;
        if ($suffix_len <= 0) {
            return ['status' => 416, 'error' => 'invalid_suffix'];
        }
        $start = max(0, $filesize - $suffix_len);
        $end = $filesize - 1;
        return ['status' => 206, 'start' => $start, 'end' => $end, 'length' => ($end - $start + 1)];
    }

    if ($raw_end === '') {
        // Open-ended range (e.g. bytes=100-)
        $start = (int)$raw_start;
        $end = $filesize - 1;
        if ($start >= $filesize) {
            return ['status' => 416, 'error' => 'start_beyond_eof'];
        }
        return ['status' => 206, 'start' => $start, 'end' => $end, 'length' => ($end - $start + 1)];
    }

    // Explicit bounded range (e.g. bytes=0-99, bytes=0-0)
    $start = (int)$raw_start;
    $end = (int)$raw_end;
    if ($start > $end || $start >= $filesize) {
        return ['status' => 416, 'error' => 'inverted_or_out_of_bounds'];
    }
    if ($end >= $filesize) {
        $end = $filesize - 1;
    }
    return ['status' => 206, 'start' => $start, 'end' => $end, 'length' => ($end - $start + 1)];
}

$file_size = 1000;

// Test vectors
$tests = [
    ['bytes=0-0', 206, 0, 0, 1],
    ['bytes=0-99', 206, 0, 99, 100],
    ['bytes=100-', 206, 100, 999, 900],
    ['bytes=-500', 206, 500, 999, 500],
    ['bytes=999999999-', 416, null, null, null],
    ['bytes=50-20', 416, null, null, null],
    ['bytes=0-50, 100-200', 416, null, null, null],
    ['bytes=malformed', 416, null, null, null],
];

foreach ($tests as $t) {
    [$header, $exp_status, $exp_start, $exp_end, $exp_len] = $t;
    $res = parseRangeHeader($header, $file_size);
    if ($res['status'] !== $exp_status) {
        die("FAIL: {$header} expected status {$exp_status}, got {$res['status']}\n");
    }
    if ($exp_status === 206) {
        if ($res['start'] !== $exp_start || $res['end'] !== $exp_end || $res['length'] !== $exp_len) {
            die("FAIL: {$header} bounds mismatch. Got start={$res['start']}, end={$res['end']}, len={$res['length']}\n");
        }
    }
}

echo "ALL_RANGE_TESTS_PASS\n";

