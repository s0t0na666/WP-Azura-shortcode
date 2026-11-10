<?php
if (!defined('ABSPATH')) { exit; }

final class AZNS_Shortcodes {
    private $stations;

    public function __construct($stations, $api) {
        $this->stations = $stations;
        foreach (['azura_player', 'azura_nowplaying', 'azura_next_track', 'azura_history', 'azura_podcasts', 'azura_schedule', 'azura_listeners', 'azura_broadcast_mode', 'azuracast_playing_next', 'azura_playing_next'] as $tag) {
            add_shortcode($tag, [$this, 'render']);
        }
    }

    public function render($attributes, $content = '', $tag = '') {
        $attributes = shortcode_atts(['station' => '', 'limit' => 10, 'native' => false], (array) $attributes, $tag);
        $station = $this->stations->get($attributes['station']);
        if (!$station) { return current_user_can('manage_options') ? '<p>Configure an AzuraCast station first.</p>' : ''; }

        $type = match ($tag) {
            'azura_player' => 'player',
            'azura_history' => 'history',
            'azura_podcasts' => 'podcasts',
            'azura_schedule' => 'schedule',
            'azura_listeners' => 'listeners',
            'azura_broadcast_mode' => 'broadcast',
            'azura_next_track', 'azuracast_playing_next', 'azura_playing_next' => 'next',
            default => 'nowplaying',
        };

        static $counter = 0;
        $id = 'azns-' . (++$counter) . '-' . wp_generate_password(4, false, false);
        wp_enqueue_style('azns');

        $native_history = ($type === 'history' && in_array($attributes['native'], [true, 1, '1', 'true'], true));

        if (in_array($type, ['player', 'history', 'podcasts', 'schedule'], true) && !$native_history) {
            $public_base = trailingslashit($station['url']) . 'public/' . rawurlencode($station['shortcode']);
            $heights = [
                'player' => 150,
                'history' => 300,
                'podcasts' => 400,
                'schedule' => 800,
            ];
            $paths = [
                'player' => '/embed',
                'history' => '/history',
                'podcasts' => '/podcasts',
                'schedule' => '/schedule/embed',
            ];
            $query = ['theme' => $station['theme']];
            if ($type === 'podcasts') { $query = ['embed' => 'true', 'theme' => $station['theme']]; }
            $embed_url = add_query_arg($query, $public_base . $paths[$type]);
            $height = $heights[$type];
            $titles = [
                'player' => 'radio player',
                'history' => 'recent playback history',
                'podcasts' => 'podcasts',
                'schedule' => 'broadcast schedule',
            ];
            $html = '<div id="' . esc_attr($id) . '" class="azns azns-' . esc_attr($type) . '-embed">';
            $html .= '<iframe src="' . esc_url($embed_url) . '" title="' . esc_attr($station['name'] . ' ' . $titles[$type]) . '" frameborder="0" allowtransparency="true" loading="lazy" style="display:block;width:100%;min-height:' . $height . 'px;height:' . $height . 'px;border:0" allow="autoplay"></iframe>';
            $html .= '</div>';
            return $html;
        }

        wp_enqueue_script('azns');
        $config = [
            'server' => $station['url'],
            'shortcode' => $station['shortcode'],
            'rest' => rest_url('azns/v1/station/' . rawurlencode($station['shortcode']) . '/'),
            'art' => $station['art'],
        ];
        wp_add_inline_script('azns', 'window.AZNS=window.AZNS||{};window.AZNS[' . wp_json_encode($id) . ']=' . wp_json_encode($config) . ';', 'before');

        $html = '<section id="' . esc_attr($id) . '" class="azns azns-' . esc_attr($type) . ' azns-' . esc_attr($station['theme']) . '" data-type="' . esc_attr($type) . '" data-limit="' . max(1, min(50, absint($attributes['limit']))) . '">';
        if ($type === 'history') {
            $html .= '<ol class="azns-history" aria-live="polite" aria-atomic="true"></ol>';
        } elseif ($type === 'next') {
            $html .= '<div class="azns-next" aria-live="polite" aria-atomic="true"></div>';
        } elseif (in_array($type, ['listeners', 'broadcast'], true)) {
            $html .= '<div class="azns-indicator" aria-live="polite" aria-atomic="true"></div>';
        } else {
            $html .= '<div class="azns-current" aria-live="polite" aria-atomic="true"></div>';
        }
        if ($type !== 'next') {
            $html .= '<small class="azns-status">Loading ' . esc_html($station['name']) . '…</small>';
        }
        $html .= '</section>';

        // Custom CSS is an administrator-controlled field and applies only to WordPress-rendered components.
        if ($station['css'] !== '') { $html .= '<style>' . wp_strip_all_tags($station['css']) . '</style>'; }
        return $html;
    }
}
