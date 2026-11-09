<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Registers dynamic Gutenberg blocks that reuse the existing shortcode renderer.
 */
final class AZNS_Blocks {
    private $stations;
    private $shortcodes;

    private const BLOCKS = [
        'azns/player' => 'azura_player',
        'azns/now-playing' => 'azura_nowplaying',
        'azns/playing-next' => 'azura_next_track',
        'azns/history' => 'azura_history',
        'azns/podcasts' => 'azura_podcasts',
        'azns/schedule' => 'azura_schedule',
        'azns/listeners' => 'azura_listeners',
        'azns/broadcast-mode' => 'azura_broadcast_mode',
    ];

    public function __construct($stations, $shortcodes) {
        $this->stations = $stations;
        $this->shortcodes = $shortcodes;
        add_action('init', [$this, 'register'], 5);
    }

    public function register() {
        if (!function_exists('register_block_type') || !function_exists('wp_register_script')) {
            return;
        }

        wp_register_script(
            'azns-blocks-editor',
            AZNS_URL . 'assets/js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            AZNS_VER,
            true
        );

        $station_options = [];
        foreach ($this->stations->all() as $station) {
            $station_options[] = [
                'label' => $station['name'] . ' (' . $station['shortcode'] . ')',
                'value' => $station['shortcode'],
            ];
        }

        wp_add_inline_script(
            'azns-blocks-editor',
            'window.AZNS_BLOCKS_CONFIG = ' . wp_json_encode(['stations' => $station_options]) . ';',
            'before'
        );

        foreach (self::BLOCKS as $block_name => $shortcode_tag) {
            register_block_type($block_name, [
                'api_version' => 2,
                'editor_script' => 'azns-blocks-editor',
                'attributes' => [
                    'station' => [
                        'type' => 'string',
                        'default' => '',
                    ],
                    'limit' => [
                        'type' => 'number',
                        'default' => 10,
                    ],
                ],
                'render_callback' => function ($attributes) use ($shortcode_tag) {
                    $attributes = is_array($attributes) ? $attributes : [];
                    $raw_station = $attributes['station'] ?? '';
                    $station = is_scalar($raw_station) ? sanitize_title((string) $raw_station) : '';
                    $raw_limit = $attributes['limit'] ?? 10;
                    $limit = is_numeric($raw_limit) ? (int) $raw_limit : 10;

                    return $this->shortcodes->render([
                        'station' => $station,
                        'limit' => max(1, min(50, $limit)),
                    ], '', $shortcode_tag);
                },
            ]);
        }
    }
}
