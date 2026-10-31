<?php
if (!defined('ABSPATH')) { exit; }

final class AZNS_Stations {
    const OPT = 'azns_settings';

    public static function url($value) {
        $value = trim((string) $value);
        if ($value === '') { return ''; }
        if (!preg_match('~^https?://~i', $value)) { $value = 'https://' . $value; }
        return untrailingslashit(esc_url_raw($value, ['http', 'https']));
    }

    public function all() {
        $settings = get_option(self::OPT, []);
        $stations = [];
        foreach (($settings['stations'] ?? []) as $row) {
            if (!is_array($row)) { continue; }
            $shortcode = sanitize_title($row['shortcode'] ?? '');
            $server = self::url($row['url'] ?? '');
            if (!$shortcode || !$server) { continue; }

            $stations[$shortcode] = [
                'name' => sanitize_text_field($row['name'] ?? $shortcode),
                'url' => $server,
                'shortcode' => $shortcode,
                'theme' => in_array(($row['theme'] ?? 'dark'), ['dark', 'light'], true) ? $row['theme'] : 'dark',
                'css' => (string) ($row['css'] ?? ''),
                'art' => !empty($row['art']),
            ];
        }
        return $stations;
    }

    public function get($identifier = '') {
        $all = $this->all();
        if (!$all) { return null; }
        $identifier = sanitize_title((string) $identifier);
        if ($identifier && isset($all[$identifier])) { return $all[$identifier]; }

        // An explicit unknown identifier must not silently resolve to another station.
        if ($identifier !== '') { return null; }

        $settings = get_option(self::OPT, []);
        $default = sanitize_title($settings['default'] ?? '');
        if (isset($all[$default])) { return $all[$default]; }
        return reset($all);
    }
}
