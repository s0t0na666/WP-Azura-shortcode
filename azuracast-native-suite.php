<?php
/**
 * Plugin Name: AzuraCast Suite
 * Description: Embeds the official AzuraCast player and adds WordPress components for now playing, Playing Next, history, podcasts and schedule.
 * Version: 1.4.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }

define('AZNS_VER', '1.4.0');
define('AZNS_DIR', plugin_dir_path(__FILE__));
define('AZNS_URL', plugin_dir_url(__FILE__));

foreach (['logger', 'stations', 'api', 'shortcodes', 'blocks', 'admin'] as $file) {
    require_once AZNS_DIR . "includes/class-azns-{$file}.php";
}

final class AZNS {
    private static $instance;
    public $stations;
    public $api;

    private function __construct() {
        $this->stations = new AZNS_Stations();
        $this->api = new AZNS_API($this->stations);
        $shortcodes = new AZNS_Shortcodes($this->stations, $this->api);
        new AZNS_Blocks($this->stations, $shortcodes);
        new AZNS_Admin($this->stations, $this->api);

        add_action('wp_enqueue_scripts', function () {
            wp_register_style('azns', AZNS_URL . 'assets/css/front.css', [], AZNS_VER);
            wp_register_script('azns', AZNS_URL . 'assets/js/front.js', [], AZNS_VER, true);
        });
        add_action('rest_api_init', [$this->api, 'routes']);
    }

    public static function get() {
        return self::$instance ?? (self::$instance = new self());
    }
}
add_action('plugins_loaded', ['AZNS', 'get']);

register_activation_hook(__FILE__, function () {
    // Station entries are always configured by the site administrator.
    add_option(AZNS_Stations::OPT, ['default' => '', 'stations' => []]);
    AZNS_Logger::init();
});
