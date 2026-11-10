<?php
/**
 * CLI regression tests for the sanitized diagnostic-log export.
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['azns_test_log_dir'] = sys_get_temp_dir() . '/azns-diagnostics-test-' . getmypid();

function wp_upload_dir() {
    return ['basedir' => $GLOBALS['azns_test_log_dir']];
}
function trailingslashit($value) {
    return rtrim((string) $value, '/\\') . '/';
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function sanitize_key($value) {
    return strtolower(preg_replace('/[^a-z0-9_\\-]/', '', (string) $value));
}

require_once __DIR__ . '/../includes/class-azns-logger.php';

$directory = AZNS_Logger::dir();
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create diagnostics test directory.');
}

$event = [
    'time' => gmdate('c'),
    'level' => 'error',
    'message' => 'Request failed: https://admin:privatepass@radio.example.test/api?token=secret-token',
    'context' => [
        'api_key' => 'top-secret-api-key',
        'nested' => [
            'Authorization' => 'Bearer access-token-value',
            'endpoint' => 'https://user:password@radio.example.test/path?access_token=query-secret',
            'note' => 'retry with ?password=embedded-secret',
        ],
    ],
];
file_put_contents($directory . '/azuracast.log', json_encode($event) . PHP_EOL);

$rows = AZNS_Logger::export_rows();
$export = json_encode($rows);

foreach ([
    'privatepass',
    'secret-token',
    'top-secret-api-key',
    'access-token-value',
    'password@',
    'query-secret',
    'embedded-secret',
] as $secret) {
    if (strpos($export, $secret) !== false) {
        throw new RuntimeException('Sensitive value leaked into diagnostics export: ' . $secret);
    }
}
if (strpos($export, '[redacted]') === false) {
    throw new RuntimeException('Expected redaction markers were not included.');
}

foreach (glob($directory . '/*') ?: [] as $file) {
    if (is_file($file)) unlink($file);
}
foreach (glob($directory . '/.*') ?: [] as $file) {
    if (is_file($file)) unlink($file);
}
rmdir($directory);
rmdir(dirname($directory));

echo "Sanitized diagnostics export tests passed.\n";
