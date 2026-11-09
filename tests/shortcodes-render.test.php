<?php
/**
 * CLI tests for shortcode rendering, embed URLs and accessibility attributes.
 * WordPress APIs are stubbed; this file is not loaded by WordPress itself.
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['azns_shortcode_test_registered'] = [];
$GLOBALS['azns_shortcode_test_enqueued'] = [];
$GLOBALS['azns_shortcode_test_inline'] = [];

function add_shortcode($tag, $callback) {
    $GLOBALS['azns_shortcode_test_registered'][$tag] = $callback;
}
function shortcode_atts($pairs, $atts, $shortcode = '') {
    return array_merge($pairs, is_array($atts) ? $atts : []);
}
function current_user_can($capability) {
    return $capability === 'manage_options';
}
function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
    return 'abcd';
}
function wp_enqueue_style($handle) {
    $GLOBALS['azns_shortcode_test_enqueued'][] = 'style:' . $handle;
}
function wp_enqueue_script($handle) {
    $GLOBALS['azns_shortcode_test_enqueued'][] = 'script:' . $handle;
}
function rest_url($path = '') {
    return '/wp-json/' . ltrim((string) $path, '/');
}
function wp_json_encode($value, $flags = 0) {
    return json_encode($value, $flags);
}
function wp_add_inline_script($handle, $data, $position = 'after') {
    $GLOBALS['azns_shortcode_test_inline'][] = [$handle, $data, $position];
    return true;
}
function esc_attr($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function esc_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function esc_url($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function trailingslashit($value) {
    return rtrim((string) $value, '/') . '/';
}
function add_query_arg($args, $url) {
    return $url . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
}
function absint($value) {
    return abs((int) $value);
}
function wp_strip_all_tags($value) {
    return strip_tags((string) $value);
}

final class AZNS_Test_Shortcode_Stations {
    private $station = [
        'name' => 'Test Radio',
        'url' => 'https://radio.example.test/base-path',
        'shortcode' => 'test_station',
        'theme' => 'dark',
        'css' => '',
        'art' => true,
    ];

    public function get($identifier = '') {
        if ($identifier === '' || $identifier === 'test_station') return $this->station;
        return null;
    }
}

require_once __DIR__ . '/../includes/class-azns-shortcodes.php';

function azns_shortcode_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$shortcodes = new AZNS_Shortcodes(new AZNS_Test_Shortcode_Stations(), null);
azns_shortcode_assert(
    isset($GLOBALS['azns_shortcode_test_registered']['azura_nowplaying'])
        && isset($GLOBALS['azns_shortcode_test_registered']['azura_next_track'])
        && isset($GLOBALS['azns_shortcode_test_registered']['azura_listeners'])
        && isset($GLOBALS['azns_shortcode_test_registered']['azura_broadcast_mode']),
    'The existing and new public shortcode tags must be registered.'
);

$current = $shortcodes->render([], '', 'azura_nowplaying');
azns_shortcode_assert(strpos($current, 'data-type="nowplaying"') !== false, 'Now Playing must render the expected widget type.');
azns_shortcode_assert(
    strpos($current, 'class="azns-current" aria-live="polite" aria-atomic="true"') !== false,
    'Now Playing metadata must be exposed as an atomic polite live region.'
);

$next = $shortcodes->render(['station' => 'test_station'], '', 'azura_next_track');
azns_shortcode_assert(strpos($next, 'data-type="next"') !== false, 'Playing Next must render the expected widget type.');
azns_shortcode_assert(
    strpos($next, 'class="azns-next" aria-live="polite" aria-atomic="true"') !== false,
    'Playing Next metadata must be exposed as an atomic polite live region.'
);

$listeners = $shortcodes->render([], '', 'azura_listeners');
azns_shortcode_assert(
    strpos($listeners, 'class="azns-indicator" aria-live="polite" aria-atomic="true"') !== false,
    'Listener count must be exposed as an atomic polite live region.'
);

$player = $shortcodes->render([], '', 'azura_player');
azns_shortcode_assert(
    strpos($player, 'https://radio.example.test/base-path/public/test_station/embed?theme=dark') !== false,
    'The official Player iframe must retain the configured AzuraCast base path and station shortcode.'
);
azns_shortcode_assert(strpos($player, 'loading="lazy"') !== false, 'AzuraCast public-page embeds must load lazily.');

$missing = $shortcodes->render([], '', 'azura_next_track');
azns_shortcode_assert($missing === '', 'A shortcode with an unknown station must not render frontend error details.');

echo "Shortcode rendering and accessibility tests passed.\n";
