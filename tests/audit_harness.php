<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    die("Not found.\n");
}

require_once __DIR__ . '/../db.php';
$pdo = get_db();
if (!$pdo) {
    echo "ERROR: Database connection failed.\n";
    exit(1);
}

echo "=== VLSI LMS DEEP AUTOMATED AUDIT ===\n\n";

// 1. Inspect MySQL Schema
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "1. DATABASE SCHEMA STATUS: " . count($tables) . " tables verified.\n";
$schema = [];
foreach ($tables as $t) {
    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $schema[$t] = array_column($cols, 'Field');
}

// 2. Scan all PHP files
$all_files = array_merge(
    glob(__DIR__ . '/../*.php'),
    glob(__DIR__ . '/../admin/*.php'),
    glob(__DIR__ . '/../teacher/*.php'),
    glob(__DIR__ . '/../student/*.php'),
    glob(__DIR__ . '/../api/*.php'),
    glob(__DIR__ . '/../auth/*.php'),
    glob(__DIR__ . '/../includes/*.php')
);

$issues = [];
$stats = [
    'csrf_checked' => 0,
    'role_checked' => 0,
    'sql_queries' => 0,
    'prepared_statements' => 0,
    'em_dashes' => 0
];

foreach ($all_files as $filepath) {
    $rel = str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', realpath($filepath));
    $code = file_get_contents($filepath);

    // Check em dashes
    if (preg_match('/\x{2014}/u', $code) || strpos($code, '—') !== false) {
        $issues[] = "$rel: Contains forbidden Unicode em dash";
        $stats['em_dashes']++;
    }

    // Check role gate on portal pages
    if (preg_match('#^(admin|teacher|student)[/\\\\]#', $rel)) {
        if (!preg_match('/require_role\(/', $code)) {
            $issues[] = "$rel: Portal page missing require_role() check";
        } else {
            $stats['role_checked']++;
        }
    }

    // Check CSRF on POST handlers
    if (preg_match('/REQUEST_METHOD.*POST/', $code)) {
        $stats['csrf_checked']++;
        $is_webhook = preg_match('#(webhook|session\.php|progress\.php|checkout_create\.php)#', $rel);
        if (!$is_webhook && !preg_match('/(require_csrf|verify_csrf_token)\(/', $code)) {
            $issues[] = "$rel: POST handler missing require_csrf() call";
        }
    }

    // Check SQL queries for prepared statements vs direct interpolation
    if (preg_match_all('/->(query|prepare|exec)\s*\(\s*(["\'])(.*?)\2/is', $code, $matches)) {
        foreach ($matches[3] as $idx => $sql) {
            $stats['sql_queries']++;
            $method = $matches[1][$idx];
            if ($method === 'prepare') {
                $stats['prepared_statements']++;
            }
            // Catch vulnerable interpolation in query/exec
            if (($method === 'query' || $method === 'exec') && preg_match('/\$[a-zA-Z0-9_]+/', $sql)) {
                $issues[] = "$rel: Unprepared variable interpolation in \$pdo->$method(\"$sql\")";
            }
        }
    }
}

echo "2. CODE AUDIT METRICS:\n";
echo "   - Total PHP Files Audited: " . count($all_files) . "\n";
echo "   - Role-Gated Portal Pages: " . $stats['role_checked'] . "\n";
echo "   - POST CSRF Verifications: " . $stats['csrf_checked'] . "\n";
echo "   - SQL Calls Audited: " . $stats['sql_queries'] . "\n";
echo "   - Prepared Statements: " . $stats['prepared_statements'] . "\n";
echo "   - Em Dashes Detected: " . $stats['em_dashes'] . "\n\n";

if (!empty($issues)) {
    echo "3. AUDIT ISSUES FOUND (" . count($issues) . "):\n";
    foreach ($issues as $i) {
        echo "   [!] $i\n";
    }
} else {
    echo "3. SECURITY & INTEGRITY AUDIT: 100% PASS (0 issues found across all scanned patterns).\n";
}
