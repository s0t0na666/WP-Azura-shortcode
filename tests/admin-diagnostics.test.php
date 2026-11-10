<?php
/**
 * CLI tests for the structure and privacy of the diagnostics report.
 */
define('ABSPATH', __DIR__ . '/');
define('AZNS_VER', '1.4.0-dev');

$GLOBALS['azns_test_options'] = [
    'azns_station_health' => [
        'example_station' => [
            'status' => 'ok',
            'checked_at' => 100,
            'last_successful_at' => 100,
            'http_status' => 200,
            'station_name' => 'Example Radio',
        ],
    ],
];

function add_action($hook, $callback, $priority = 10) {}
function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['azns_test_options']) ? $GLOBALS['azns_test_options'][$name] : $default;
}
function absint($value) {
    return abs((int) $value);
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function get_bloginfo($show = '') {
    return '6.8-test';
}
function is_multisite() {
    return false;
}

final class AZNS_Logger {
    public static function export_rows() {
        return [['level' => 'info', 'message' => 'Redacted sample log']];
    }
}
final class AZNS_Test_Diagnostic_Stations {
    public function all() {
        return [
            'example_station' => [
                'shortcode' => 'example_station',
                'name' => 'Example Radio',
                'url' => 'https://private-host.example.test/secret-subdirectory',
                'theme' => 'dark',
                'art' => true,
                'css' => '.private-selector { color: red; }',
            ],
        ];
    }
}

require_once __DIR__ . '/../includes/class-azns-admin.php';

$admin = new AZNS_Admin(new AZNS_Test_Diagnostic_Stations(), null);
$report = $admin->diagnostics_report();
$encoded = json_encode($report);

if (($report['stations'][0]['connection']['status'] ?? '') !== 'ok') {
    throw new RuntimeException('Station connection status is missing from diagnostics.');
}
if (($report['environment']['wordpress_version'] ?? '') !== '6.8-test') {
    throw new RuntimeException('WordPress version is missing from diagnostics.');
}
foreach (['private-host.example.test', 'secret-subdirectory', 'private-selector', 'color: red'] as $private_value) {
    if (strpos($encoded, $private_value) !== false) {
        throw new RuntimeException('Private station configuration leaked into diagnostics report.');
    }
}
if (($report['privacy']['server_urls'] ?? '') !== 'omitted') {
    throw new RuntimeException('The report must explicitly declare that station URLs are omitted.');
}

echo "Diagnostics report privacy tests passed.\n";
