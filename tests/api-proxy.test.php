<?php
/**
 * CLI regression tests for the public AzuraCast REST proxy and API client.
 * WordPress functions/classes are stubbed; this file is not loaded by WordPress.
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['azns_api_test_cache'] = [];
$GLOBALS['azns_api_test_ttls'] = [];
$GLOBALS['azns_api_test_requests'] = [];
$GLOBALS['azns_api_test_response'] = null;
$GLOBALS['azns_api_test_routes'] = [];

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct($code, $message, $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) {}
function register_rest_route($namespace, $route, $args) {
    $GLOBALS['azns_api_test_routes'][$namespace . $route] = $args;
}
function rest_ensure_response($data) { return new WP_REST_Response($data, 200); }
function get_transient($key) {
    return array_key_exists($key, $GLOBALS['azns_api_test_cache'])
        ? $GLOBALS['azns_api_test_cache'][$key]
        : false;
}
function set_transient($key, $value, $ttl) {
    $GLOBALS['azns_api_test_cache'][$key] = $value;
    $GLOBALS['azns_api_test_ttls'][$key] = $ttl;
    return true;
}
function wp_remote_get($url, $args = []) {
    $GLOBALS['azns_api_test_requests'][] = ['url' => $url, 'args' => $args];
    return $GLOBALS['azns_api_test_response'];
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function rawurlencode($value) {
    return urlencode((string) $value);
}

final class AZNS_Logger {
    public static $entries = [];
    public static function log($level, $message, $context = []) {
        self::$entries[] = compact('level', 'message', 'context');
    }
}
final class AZNS_Test_API_Stations {
    public function get($shortcode = '') {
        if ($shortcode !== 'test_station') return null;
        return [
            'url' => 'https://radio.example.test/base-path',
            'shortcode' => 'test_station',
        ];
    }
}
final class AZNS_Test_REST_Request implements ArrayAccess {
    private $values;
    public function __construct($values) { $this->values = $values; }
    public function offsetExists($offset): bool { return array_key_exists($offset, $this->values); }
    public function offsetGet($offset): mixed { return $this->values[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->values[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->values[$offset]); }
    public function get_param($name) { return $this->values[$name] ?? null; }
}

require_once __DIR__ . '/../includes/class-azns-api.php';

function azns_api_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function azns_api_reset_transport($response) {
    $GLOBALS['azns_api_test_response'] = $response;
    $GLOBALS['azns_api_test_requests'] = [];
    $GLOBALS['azns_api_test_cache'] = [];
    $GLOBALS['azns_api_test_ttls'] = [];
}

$api = new AZNS_API(new AZNS_Test_API_Stations());

azns_api_reset_transport([
    'status' => 200,
    'body' => json_encode(['station' => ['name' => 'Test Radio']]),
]);
$data = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'nowplaying');
azns_api_assert($data['station']['name'] === 'Test Radio', 'A valid Now Playing payload must be returned.');
azns_api_assert(count($GLOBALS['azns_api_test_requests']) === 1, 'A cache miss must make exactly one upstream request.');
$nowplaying_request = $GLOBALS['azns_api_test_requests'][0];
azns_api_assert(
    $nowplaying_request['url'] === 'https://radio.example.test/base-path/api/nowplaying/test_station',
    'Now Playing must use the configured base path and station shortcode.'
);
azns_api_assert(
    ($nowplaying_request['args']['redirection'] ?? null) === 0
        && ($nowplaying_request['args']['sslverify'] ?? null) === true
        && ($nowplaying_request['args']['timeout'] ?? null) === 12,
    'Upstream requests must use TLS verification, disable redirects and enforce a timeout.'
);
$ttl = reset($GLOBALS['azns_api_test_ttls']);
azns_api_assert($ttl === 5, 'Now Playing snapshots must use the short five-second cache.');
$cached = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'nowplaying');
azns_api_assert($cached === $data && count($GLOBALS['azns_api_test_requests']) === 1, 'A valid transient must prevent a duplicate upstream request.');

azns_api_reset_transport(['status' => 200, 'body' => '{"podcasts":[]}']);
$api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'podcasts');
$podcast_request = $GLOBALS['azns_api_test_requests'][0];
azns_api_assert(
    $podcast_request['url'] === 'https://radio.example.test/base-path/api/station/test_station/public/podcasts',
    'Podcast listing must use the public podcast endpoint.'
);
azns_api_assert(reset($GLOBALS['azns_api_test_ttls']) === 300, 'Non-live public resources must use the five-minute cache.');

azns_api_reset_transport(['status' => 200, 'body' => '{"episodes":[]}']);
$api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'podcast_episodes', 'episode/one');
$episode_request = $GLOBALS['azns_api_test_requests'][0];
azns_api_assert(
    $episode_request['url'] === 'https://radio.example.test/base-path/api/station/test_station/public/podcast/episode%2Fone/episodes',
    'Podcast IDs must be URL-encoded as a single path segment.'
);

azns_api_reset_transport(['status' => 200, 'body' => '{"schedule":[]}']);
$api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'schedule');
azns_api_assert(
    $GLOBALS['azns_api_test_requests'][0]['url'] === 'https://radio.example.test/base-path/api/station/test_station/schedule',
    'Schedule requests must use the station schedule endpoint.'
);

$invalid_kind = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'arbitrary');
azns_api_assert(is_wp_error($invalid_kind) && $invalid_kind->get_error_code() === 'azns_kind', 'Unknown API resources must be denied.');
$invalid_id = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'podcast_episodes', ['array']);
azns_api_assert(is_wp_error($invalid_id), 'Non-scalar podcast IDs must be rejected.');
$empty_id = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'podcast_episodes', '   ');
azns_api_assert(is_wp_error($empty_id), 'Empty podcast IDs must be rejected.');

azns_api_reset_transport(['status' => 503, 'body' => '{}']);
$http_error = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'schedule');
azns_api_assert(is_wp_error($http_error) && $http_error->get_error_code() === 'azns_api', 'Non-2xx upstream responses must become WP_Error.');

azns_api_reset_transport(['status' => 200, 'body' => '{invalid-json']);
$malformed = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'schedule');
azns_api_assert(is_wp_error($malformed) && $malformed->get_error_code() === 'azns_api', 'Malformed JSON must become WP_Error.');

azns_api_reset_transport(new WP_Error('http_request_failed', 'Connection timed out'));
$network_error = $api->fetch((new AZNS_Test_API_Stations())->get('test_station'), 'schedule');
azns_api_assert(is_wp_error($network_error) && $network_error->get_error_code() === 'http_request_failed', 'Transport WP_Error values must be propagated.');

$api->routes();
$route = $GLOBALS['azns_api_test_routes']['azns/v1/station/(?P<shortcode>[a-z0-9_-]+)/(?P<resource>nowplaying|podcasts|schedule|podcast-episodes)'];
azns_api_assert(($route['permission_callback'] ?? null) === '__return_true', 'Public read-only routes must remain available without authentication.');
azns_api_assert(
    ($route['methods'] ?? '') === 'GET' && isset($route['callback']),
    'The public proxy must register a GET callback.'
);

$not_found = ($route['callback'])(new AZNS_Test_REST_Request([
    'shortcode' => 'unknown_station',
    'resource' => 'nowplaying',
]));
azns_api_assert(is_wp_error($not_found) && ($not_found->get_error_data()['status'] ?? 0) === 404, 'Unconfigured stations must return HTTP 404.');

$missing_episode_id = ($route['callback'])(new AZNS_Test_REST_Request([
    'shortcode' => 'test_station',
    'resource' => 'podcast-episodes',
    'podcast_id' => '',
]));
azns_api_assert(is_wp_error($missing_episode_id) && ($missing_episode_id->get_error_data()['status'] ?? 0) === 400, 'Episode requests without an ID must return HTTP 400.');

azns_api_reset_transport(['status' => 502, 'body' => '{}']);
$upstream_error = ($route['callback'])(new AZNS_Test_REST_Request([
    'shortcode' => 'test_station',
    'resource' => 'schedule',
]));
azns_api_assert($upstream_error instanceof WP_REST_Response && $upstream_error->status === 502, 'Upstream failures must be translated to REST HTTP 502.');

azns_api_reset_transport(['status' => 200, 'body' => '{"schedule":[]}']);
$success = ($route['callback'])(new AZNS_Test_REST_Request([
    'shortcode' => 'test_station',
    'resource' => 'schedule',
]));
azns_api_assert($success instanceof WP_REST_Response && $success->status === 200, 'Successful proxy responses must be returned as REST responses.');

echo "AzuraCast API and REST proxy tests passed.\n";
