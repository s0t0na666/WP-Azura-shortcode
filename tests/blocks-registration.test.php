<?php
/**
 * CLI tests for dynamic Gutenberg block registration and server rendering.
 * WordPress APIs are stubbed; this file is not loaded by WordPress itself.
 */
define('ABSPATH', __DIR__ . '/');
define('AZNS_URL', 'https://plugin.example.test/');
define('AZNS_VER', '1.4.0');

$GLOBALS['azns_block_test_actions'] = [];
$GLOBALS['azns_block_test_scripts'] = [];
$GLOBALS['azns_block_test_inline'] = [];
$GLOBALS['azns_block_test_registered'] = [];

function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['azns_block_test_actions'][$hook][] = $callback;
}
function wp_register_script($handle, $src, $deps = [], $version = false, $in_footer = false) {
    $GLOBALS['azns_block_test_scripts'][$handle] = compact('src', 'deps', 'version', 'in_footer');
    return true;
}
function wp_add_inline_script($handle, $data, $position = 'after') {
    $GLOBALS['azns_block_test_inline'][] = compact('handle', 'data', 'position');
    return true;
}
function wp_json_encode($value, $flags = 0) {
    return json_encode($value, $flags);
}
function register_block_type($name, $args = []) {
    $GLOBALS['azns_block_test_registered'][$name] = $args;
    return true;
}
function sanitize_title($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
    return trim($value, '-');
}

final class AZNS_Test_Block_Stations {
    public function all() {
        return [
            'mugadavanje' => [
                'name' => 'Mugadavanje Radio',
                'shortcode' => 'mugadavanje',
            ],
            'second_station' => [
                'name' => 'Second Radio',
                'shortcode' => 'second_station',
            ],
        ];
    }
}

final class AZNS_Test_Block_Shortcodes {
    public $calls = [];

    public function render($attributes, $content = '', $tag = '') {
        $this->calls[] = [
            'attributes' => $attributes,
            'content' => $content,
            'tag' => $tag,
        ];
        return '<rendered tag="' . $tag . '">';
    }
}

require_once __DIR__ . '/../includes/class-azns-blocks.php';

function azns_block_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$stations = new AZNS_Test_Block_Stations();
$shortcodes = new AZNS_Test_Block_Shortcodes();
$blocks = new AZNS_Blocks($stations, $shortcodes);

azns_block_assert(isset($GLOBALS['azns_block_test_actions']['init']), 'Block registration must be scheduled on init.');
$blocks->register();

$expected = [
    'azns/player' => 'azura_player',
    'azns/now-playing' => 'azura_nowplaying',
    'azns/playing-next' => 'azura_next_track',
    'azns/history' => 'azura_history',
    'azns/podcasts' => 'azura_podcasts',
    'azns/schedule' => 'azura_schedule',
    'azns/listeners' => 'azura_listeners',
    'azns/broadcast-mode' => 'azura_broadcast_mode',
];

$registered = $GLOBALS['azns_block_test_registered'];
azns_block_assert(array_keys($registered) === array_keys($expected), 'All eight dynamic Gutenberg blocks must register in the defined order.');
azns_block_assert(isset($GLOBALS['azns_block_test_scripts']['azns-blocks-editor']), 'The Gutenberg editor bundle must be registered.');
azns_block_assert(
    $GLOBALS['azns_block_test_scripts']['azns-blocks-editor']['deps'] === ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
    'The editor bundle must declare its WordPress dependencies.'
);
azns_block_assert(count($GLOBALS['azns_block_test_inline']) === 1, 'The configured station list must be supplied once to the editor.');
$inline_config = $GLOBALS['azns_block_test_inline'][0]['data'];
$config_prefix = 'window.AZNS_BLOCKS_CONFIG = ';
$config = str_starts_with($inline_config, $config_prefix)
    ? json_decode(rtrim(substr($inline_config, strlen($config_prefix)), ';'), true)
    : null;
azns_block_assert(
    $config['stations'] === [
        ['label' => 'Mugadavanje Radio (mugadavanje)', 'value' => 'mugadavanje'],
        ['label' => 'Second Radio (second_station)', 'value' => 'second_station'],
    ],
    'The editor configuration must include only configured station names and shortcodes.'
);

foreach ($expected as $block_name => $shortcode_tag) {
    $definition = $registered[$block_name];
    azns_block_assert(($definition['api_version'] ?? null) === 2, 'Each block must use the declared Block API version.');
    azns_block_assert(($definition['editor_script'] ?? '') === 'azns-blocks-editor', 'Each block must use the shared editor script.');
    azns_block_assert(isset($definition['render_callback']) && is_callable($definition['render_callback']), 'Each block must have a dynamic server render callback.');
    azns_block_assert(($definition['attributes']['station']['default'] ?? null) === '', 'Each block must default to the configured default station.');
    azns_block_assert(($definition['attributes']['limit']['default'] ?? null) === 10, 'Each block must declare the default entry limit.');

    $rendered = $definition['render_callback']([
        'station' => 'mugadavanje',
        'limit' => 15,
    ]);
    $call = end($shortcodes->calls);
    azns_block_assert($rendered === '<rendered tag="' . $shortcode_tag . '">', 'A block must return output from the matching shortcode renderer.');
    azns_block_assert($call['tag'] === $shortcode_tag, 'A block must delegate to its matching shortcode tag.');
    azns_block_assert($call['attributes'] === ['station' => 'mugadavanje', 'limit' => 15], 'Block attributes must pass to the shared shortcode renderer.');
}

$history = $registered['azns/history']['render_callback'];
$history(['station' => 'Mugadavanje 01', 'limit' => 0]);
$call = end($shortcodes->calls);
azns_block_assert(
    $call['attributes'] === ['station' => 'mugadavanje-01', 'limit' => 1],
    'Station attributes must be sanitized and history limits must clamp to a minimum of one.'
);

$history(['station' => ['not', 'scalar'], 'limit' => 'not-a-number']);
$call = end($shortcodes->calls);
azns_block_assert(
    $call['attributes'] === ['station' => '', 'limit' => 10],
    'Malformed block attributes must safely fall back to the default station and limit.'
);

$history(['station' => 'second_station', 'limit' => 500]);
$call = end($shortcodes->calls);
azns_block_assert(
    $call['attributes'] === ['station' => 'second_station', 'limit' => 50],
    'History limits must clamp to the maximum of fifty.'
);

echo "Gutenberg registration and server-render tests passed.\n";
