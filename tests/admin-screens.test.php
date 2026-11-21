<?php
/** Regression checks for the admin views and stored configuration round trip. */
define('ABSPATH', __DIR__);
define('AZNS_VER', '1.5.0');
define('AZNS_URL', 'https://example.test/plugin/');

$settings = [
    'default' => 'second',
    'stations' => [
        ['shortcode' => 'first', 'name' => '', 'url' => 'https://radio.example.test', 'theme' => 'dark', 'css' => '', 'art' => false],
        ['shortcode' => 'second', 'name' => 'Second & Live', 'url' => 'https://radio.example.test', 'theme' => 'light', 'css' => '.test { color: white; }', 'art' => true],
    ],
];
$allowed = true;
$assets = [];
$registered = [];
$redirect = '';
$clear_count = 0;

function add_action(...$args) {}
function add_menu_page(...$args) { return 'toplevel_page_' . $args[3]; }
function add_submenu_page(...$args) { return 'azuracast_page_' . $args[4]; }
function add_shortcode($name, $callback) { $GLOBALS['registered'][] = $name; }
function wp_enqueue_style($name, $url, ...$args) { $GLOBALS['assets'][] = $url; }
function wp_enqueue_script($name, $url, ...$args) { $GLOBALS['assets'][] = $url; }
function current_user_can($capability) { return $GLOBALS['allowed'] && $capability === 'manage_options'; }
function get_option($key, $default = false) { return $key === 'azns_settings' ? $GLOBALS['settings'] : $default; }
function update_option($key, $value, $autoload) {
    ensure($key === 'azns_settings' && $autoload === false, 'Settings option or autoload policy changed.');
    $GLOBALS['settings'] = $value;
}
function sanitize_title($value) { return strtolower(trim($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function wp_parse_url($url) { return parse_url($url); }
function esc_url_raw($url, $protocols) { return $url; }
function untrailingslashit($value) { return rtrim($value, '/\\'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function selected($current, $value) { if ($current === $value) echo 'selected'; }
function checked($value) { if ($value) echo 'checked'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($action) . '">'; }
function check_admin_referer($action) { ensure(($_POST['_wpnonce'] ?? '') === $action, 'Missing action nonce.'); }
function submit_button($label, $class, $name, $wrap) { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function wp_unslash($value) { return $value; }
function add_query_arg($args, $url) { return $url . '&' . http_build_query($args); }
function wp_safe_redirect($url) { $GLOBALS['redirect'] = $url; throw new Redirect(); }

final class Redirect extends RuntimeException {}
final class AZNS_Logger {
    public static function log(...$args) {}
    public static function tail() { return 'Sample <entry>'; }
    public static function clear() { $GLOBALS['clear_count']++; }
}

function ensure($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function render_admin($callback) {
    ob_start();
    $callback();
    return ob_get_clean();
}

require_once __DIR__ . '/../includes/class-azns-stations.php';
require_once __DIR__ . '/../includes/class-azns-shortcodes.php';
require_once __DIR__ . '/../includes/class-azns-admin.php';

$stations = new AZNS_Stations();
new AZNS_Shortcodes($stations, null);
$admin = new AZNS_Admin($stations);
$admin->menu();
$admin->enqueue('dashboard');
ensure($assets === [], 'Plugin assets appeared on an unrelated admin screen.');
foreach (['toplevel_page_azns', 'azuracast_page_azns-shortcodes', 'azuracast_page_azns-logs'] as $screen) {
    $assets = [];
    $admin->enqueue($screen);
    ensure($assets === [AZNS_URL . 'assets/css/admin.css', AZNS_URL . 'assets/js/admin.js'], 'A plugin screen is missing admin assets.');
}

$cards = render_admin([$admin, 'page']);
$catalog = render_admin([$admin, 'shortcodes']);
$logs = render_admin([$admin, 'logs']);
ensure(str_contains($cards, 'name="name[0]" value=""'), 'An empty saved display name was replaced with its shortcode.');
ensure(str_contains($cards, 'name="css[1]"'), 'Station CSS field no longer submits its original key.');
ensure(str_contains($cards, 'value="second" selected'), 'The saved default station was not selected.');
ensure(substr_count($catalog, 'data-copy-shortcode') === 9, 'The catalog must offer nine copy buttons.');
foreach ($registered as $tag) ensure(str_contains($catalog, '[' . $tag . ']'), 'Registered shortcode missing from catalog: ' . $tag);
ensure(str_contains($logs, 'name="confirm_clear" value="1" required'), 'Clearing logs must require explicit confirmation.');
ensure(str_contains($logs, 'Sample &lt;entry&gt;'), 'Log text must be escaped.');
$allowed = false;
foreach (['page', 'shortcodes', 'logs'] as $screen) {
    ensure(render_admin([$admin, $screen]) === '', 'Admin screen is visible to an unauthorized user.');
}
$allowed = true;

$previous = $settings;
$_POST = [
    '_wpnonce' => 'azns_save',
    'default' => 'second',
    'shortcode' => ['first', 'second'],
    'name' => ['', 'Second & Live'],
    'url' => ['https://radio.example.test', 'https://radio.example.test'],
    'theme' => ['dark', 'light'],
    'css' => ['', '.test { color: white; }'],
    'art' => [1 => '1'],
];
try { $admin->save(); } catch (Redirect) {}
ensure(array_values($settings['stations']) == $previous['stations'] && $settings['default'] === $previous['default'], 'Saving the existing form altered 1.4.1 settings.');

$_POST['shortcode'][] = 'second';
$_POST['shortcode'][] = 'broken';
$_POST['url'][] = 'https://radio.example.test';
$_POST['url'][] = 'https://user:password@radio.example.test';
try { $admin->save(); } catch (Redirect) {}
ensure(str_contains($redirect, 'duplicate=1') && str_contains($redirect, 'invalid_url=1'), 'Duplicate and invalid URL feedback were lost.');
ensure($settings['stations'] == $previous['stations'], 'Invalid rows altered valid station settings.');

$_POST['autodj_label'] = [0 => ' <b>Studio Mix</b> ', 1 => ['invalid']];
$_POST['live_label'] = [1 => 'Live from Belgrade'];
$_POST['upcoming_label'] = [0 => ' <b>Coming up</b> '];
try { $admin->save(); } catch (Redirect) {}
ensure(($stations->get('first')['autodj_label'] ?? '') === 'Studio Mix', 'Custom AutoDJ label did not round trip or was not sanitized.');
ensure(!isset($stations->get('second')['autodj_label']), 'A malformed AutoDJ label was accepted.');
ensure(($stations->get('second')['live_label'] ?? '') === 'Live from Belgrade', 'Custom live label was not saved for its own station.');
ensure(($stations->get('first')['upcoming_label'] ?? '') === 'Coming up', 'Custom upcoming label was not saved for its station.');
$_POST['autodj_label'][0] = '';
$_POST['upcoming_label'][0] = '';
try { $admin->save(); } catch (Redirect) {}
ensure(!isset($stations->get('first')['autodj_label']) && !isset($stations->get('first')['upcoming_label']), 'Clearing custom labels must restore the defaults.');

$_POST = ['_wpnonce' => 'azns_clear'];
try { $admin->clear(); } catch (Redirect) {}
ensure($clear_count === 0, 'Logs were cleared without confirmation.');
$_POST['confirm_clear'] = '1';
try { $admin->clear(); } catch (Redirect) {}
ensure($clear_count === 1, 'Confirmed log clearing did not run.');

echo "Admin screens and settings regression tests passed.\n";
