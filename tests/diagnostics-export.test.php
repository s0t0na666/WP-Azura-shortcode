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
function wp_mkdir_p($path) {
    return is_dir($path) || mkdir($path, 0775, true);
}
function wp_json_encode($value) {
    return json_encode($value);
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

$file = $directory . '/azuracast.log';
$large_context = ['detail' => str_repeat('x', 530000)];
AZNS_Logger::log('info', 'First recent entry', $large_context);
AZNS_Logger::log('info', 'Second recent entry', $large_context);
clearstatcache();
if (!is_file($file . '.1') || filesize($file) > 1048576 || filesize($file . '.1') > 1048576) {
    throw new RuntimeException('Diagnostic rotation must keep both files within their size limit.');
}
$messages = array_column(AZNS_Logger::export_rows(), 'message');
if (array_slice($messages, -2) !== ['First recent entry', 'Second recent entry']) {
    throw new RuntimeException('The export must include recent entries across the rotation boundary.');
}

file_put_contents($file, str_repeat('x', 1100000) . PHP_EOL . json_encode(['message' => 'Legacy recent entry']) . PHP_EOL);
AZNS_Logger::log('info', 'After upgrade');
clearstatcache();
$messages = array_column(AZNS_Logger::export_rows(), 'message');
if (filesize($file . '.1') > 1048576 || $messages !== ['Legacy recent entry', 'After upgrade']) {
    throw new RuntimeException('An oversized legacy log must rotate without losing its recent complete entry.');
}

AZNS_Logger::clear();
if (AZNS_Logger::tail() !== '' || is_file($file) || is_file($file . '.1')) {
    throw new RuntimeException('Clearing diagnostics must remove the active file and archive.');
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
