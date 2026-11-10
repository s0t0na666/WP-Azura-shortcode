<?php
/**
 * CLI regression tests for station normalization, URL validation and selection.
 * WordPress functions are stubbed; this file is not loaded by WordPress itself.
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['azns_station_test_options'] = [];

function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['azns_station_test_options'])
        ? $GLOBALS['azns_station_test_options'][$name]
        : $default;
}
function wp_parse_url($url) {
    return parse_url($url);
}
function esc_url_raw($url, $protocols = null) {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme'])) return '';
    if (is_array($protocols) && !in_array(strtolower($parts['scheme']), $protocols, true)) return '';
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}
function untrailingslashit($value) {
    return rtrim((string) $value, '/');
}
function sanitize_title($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
    return trim($value, '-');
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function sanitize_textarea_field($value) {
    return trim(strip_tags((string) $value));
}

require_once __DIR__ . '/../includes/class-azns-stations.php';

function azns_station_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$valid_urls = [
    'https://radio.example.test' => 'https://radio.example.test',
    'https://radio.example.test/' => 'https://radio.example.test',
    'radio.example.test' => 'https://radio.example.test',
    'https://radio.example.test/azuracast' => 'https://radio.example.test/azuracast',
    'http://radio.example.test:8080/radio' => 'http://radio.example.test:8080/radio',
];
foreach ($valid_urls as $input => $expected) {
    azns_station_assert(AZNS_Stations::url($input) === $expected, 'A valid station URL was rejected or normalized incorrectly: ' . $input);
}

$invalid_urls = [
    '',
    'not a URL',
    'javascript://radio.example.test',
    'file:///etc/passwd',
    'ftp://radio.example.test',
    'https://user:password@radio.example.test',
    'https://radio.example.test/?token=secret',
    'https://radio.example.test/#fragment',
    'https://radio.example.test:0',
    'https://radio.example.test:65536',
    ['https://radio.example.test'],
    null,
    123,
];
foreach ($invalid_urls as $input) {
    azns_station_assert(AZNS_Stations::url($input) === '', 'An invalid station URL was accepted.');
}

$GLOBALS['azns_station_test_options']['azns_settings'] = [
    'default' => 'second_station',
    'stations' => [
        [
            'shortcode' => 'First Station',
            'name' => '  First <b>Radio</b>  ',
            'url' => 'https://radio-one.example.test/base/',
            'theme' => 'light',
            'css' => "  .player { color: red; }  ",
            'art' => '1',
        ],
        [
            'shortcode' => 'first-station',
            'name' => 'Duplicate must be ignored',
            'url' => 'https://duplicate.example.test',
        ],
        [
            'shortcode' => 'second_station',
            'name' => 'Second Radio',
            'url' => 'radio-two.example.test',
            'theme' => 'invalid-theme',
            'css' => '',
            'art' => '',
        ],
        [
            'shortcode' => 'invalid-url',
            'url' => 'https://user:pass@bad.example.test',
        ],
        'not-a-row',
        [
            'shortcode' => ['not', 'scalar'],
            'url' => 'https://bad.example.test',
        ],
    ],
];

$stations = new AZNS_Stations();
$all = $stations->all();
azns_station_assert(count($all) === 2, 'Station loading must skip duplicate, malformed and invalid rows.');
azns_station_assert(isset($all['first-station'], $all['second_station']), 'Station shortcodes must be normalized and retained.');
azns_station_assert($all['first-station']['name'] === 'First Radio', 'Station names must be sanitized.');
azns_station_assert($all['first-station']['theme'] === 'light', 'A supported theme must be retained.');
azns_station_assert($all['first-station']['url'] === 'https://radio-one.example.test/base', 'The configured base path must be preserved.');
azns_station_assert($all['second_station']['theme'] === 'dark', 'An invalid theme must safely fall back to dark.');
azns_station_assert($all['first-station']['art'] === true && $all['second_station']['art'] === false, 'Artwork settings must normalize to booleans.');

$default = $stations->get();
azns_station_assert($default['shortcode'] === 'second_station', 'The configured default station must be selected.');
$explicit = $stations->get('first-station');
azns_station_assert($explicit['name'] === 'First Radio', 'An explicitly requested station must be selected.');
azns_station_assert($stations->get('missing-station') === null, 'An unknown explicit shortcode must not silently select another station.');

$GLOBALS['azns_station_test_options']['azns_settings']['default'] = 'removed-station';
$fallback = $stations->get();
azns_station_assert($fallback['shortcode'] === 'first-station', 'An invalid default must fall back to the first valid station.');

$GLOBALS['azns_station_test_options']['azns_settings'] = ['stations' => 'invalid'];
azns_station_assert($stations->all() === [], 'Malformed station option data must return an empty station list.');

echo "Station configuration and URL validation tests passed.\n";
