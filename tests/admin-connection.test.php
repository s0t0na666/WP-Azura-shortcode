<?php
/**
 * Lightweight CLI tests for the administrator station connection action.
 * This file provides WordPress stubs; it is not loaded by WordPress itself.
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['azns_test_options'] = [];
$GLOBALS['azns_test_capability'] = true;
$GLOBALS['azns_test_response'] = null;
$GLOBALS['azns_test_request'] = null;
$GLOBALS['azns_test_hooks'] = [];
$GLOBALS['azns_test_json'] = [];

final class AZNS_Test_Json_Response extends Exception {
    public $success;
    public $data;
    public $status;

    public function __construct($success, $data, $status) {
        parent::__construct('JSON response');
        $this->success = $success;
        $this->data = $data;
        $this->status = $status;
    }
}

final class AZNS_Test_WP_Error {
    private $message;

    public function __construct($message) {
        $this->message = $message;
    }

    public function get_error_message() {
        return $this->message;
    }
}

function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['azns_test_hooks'][$hook][] = $callback;
}
function current_user_can($capability) {
    return $capability === 'manage_options' && $GLOBALS['azns_test_capability'];
}
function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['azns_test_options']) ? $GLOBALS['azns_test_options'][$name] : $default;
}
function update_option($name, $value, $autoload = null) {
    $GLOBALS['azns_test_options'][$name] = $value;
    return true;
}
function absint($value) {
    return abs((int) $value);
}
function check_ajax_referer($action, $query_arg = false) {
    $nonce = isset($_POST[$query_arg]) ? $_POST[$query_arg] : '';
    if ($action !== 'azns_test_connection' || $nonce !== 'valid-test-nonce') {
        throw new AZNS_Test_Json_Response(false, ['message' => 'Invalid nonce'], 403);
    }
    return true;
}
function wp_send_json_error($data = null, $status_code = 400) {
    throw new AZNS_Test_Json_Response(false, $data, $status_code);
}
function wp_send_json_success($data = null, $status_code = null) {
    throw new AZNS_Test_Json_Response(true, $data, $status_code ?: 200);
}
function wp_unslash($value) {
    return $value;
}
function sanitize_title($value) {
    return strtolower(trim(preg_replace('/[^a-z0-9_-]+/i', '-', (string) $value), '-'));
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function trailingslashit($value) {
    return rtrim((string) $value, '/') . '/';
}
function is_wp_error($value) {
    return $value instanceof AZNS_Test_WP_Error;
}
function wp_remote_get($url, $args = []) {
    $GLOBALS['azns_test_request'] = ['url' => $url, 'args' => $args];
    return $GLOBALS['azns_test_response'];
}
function wp_remote_retrieve_response_code($response) {
    return $response['status'];
}
function wp_remote_retrieve_body($response) {
    return $response['body'];
}

final class AZNS_Logger {
    public static function log($level, $message, $context = []) {
        $GLOBALS['azns_test_json'][] = [$level, $message, $context];
    }
}

final class AZNS_Test_Stations {
    public function get($shortcode = '') {
        if ($shortcode !== 'test_station') {
            return null;
        }
        return [
            'url' => 'https://radio.example.test/radio-proxy',
            'shortcode' => 'test_station',
        ];
    }
}

require_once __DIR__ . '/../includes/class-azns-admin.php';

function azns_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function azns_test_run($post, $capability, $response) {
    $_POST = $post;
    $GLOBALS['azns_test_capability'] = $capability;
    $GLOBALS['azns_test_response'] = $response;
    $GLOBALS['azns_test_request'] = null;

    $admin = new AZNS_Admin(new AZNS_Test_Stations(), null);
    try {
        $admin->ajax_test_connection();
    } catch (AZNS_Test_Json_Response $result) {
        return $result;
    }

    throw new RuntimeException('Expected the AJAX handler to return a JSON response.');
}

$valid_post = ['nonce' => 'valid-test-nonce', 'shortcode' => 'test_station'];

$result = azns_test_run($valid_post, false, null);
azns_test_assert(!$result->success && $result->status === 403, 'Permission checks must reject non-admin users.');

$result = azns_test_run(['nonce' => 'wrong', 'shortcode' => 'test_station'], true, null);
azns_test_assert(!$result->success && $result->status === 403, 'Nonce validation must reject invalid requests.');

$result = azns_test_run(['nonce' => 'valid-test-nonce', 'shortcode' => 'missing_station'], true, null);
azns_test_assert(!$result->success && $result->status === 404, 'Unknown stations must be rejected.');

$result = azns_test_run($valid_post, true, [
    'status' => 200,
    'body' => json_encode(['station' => ['name' => 'Test Station']]),
]);
azns_test_assert($result->success && $result->status === 200, 'A valid AzuraCast payload should pass.');
azns_test_assert($result->data['station_name'] === 'Test Station', 'The station name should be returned.');
$health = get_option('azns_station_health', []);
azns_test_assert($health['test_station']['status'] === 'ok', 'Successful checks must save a reachable health state.');
azns_test_assert($health['test_station']['last_successful_at'] > 0, 'Successful checks must save the last successful timestamp.');
azns_test_assert(
    $GLOBALS['azns_test_request']['url'] === 'https://radio.example.test/radio-proxy/api/nowplaying/test_station',
    'The test must use the configured base path and station shortcode.'
);
azns_test_assert(
    $GLOBALS['azns_test_request']['args']['redirection'] === 0
        && $GLOBALS['azns_test_request']['args']['sslverify'] === true,
    'The connection test must not follow redirects and must verify TLS.'
);

$last_successful = $health['test_station']['last_successful_at'];
$result = azns_test_run($valid_post, true, ['status' => 503, 'body' => '{}']);
azns_test_assert(!$result->success && $result->status === 502, 'Upstream HTTP errors must be reported.');
$health = get_option('azns_station_health', []);
azns_test_assert($health['test_station']['status'] === 'error', 'Failed checks must save an unreachable health state.');
azns_test_assert($health['test_station']['last_successful_at'] === $last_successful, 'Failed checks must preserve the last successful timestamp.');

$result = azns_test_run($valid_post, true, ['status' => 200, 'body' => '{"not_station":true}']);
azns_test_assert(!$result->success && $result->status === 502, 'Malformed Now Playing payloads must be rejected.');

$result = azns_test_run($valid_post, true, new AZNS_Test_WP_Error('Connection timed out'));
azns_test_assert(!$result->success && $result->status === 502, 'Network errors must be reported.');

echo "Administrator connection tests passed.\n";
