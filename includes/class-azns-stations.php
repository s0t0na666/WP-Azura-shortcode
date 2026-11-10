<?php
if (!defined('ABSPATH')) { exit; }
final class AZNS_Stations {
    const OPT = 'azns_settings';
    public static function url($value) {
        if (!is_scalar($value)) return '';
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 2048) return '';
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) $value = 'https://' . $value;
        $parts = wp_parse_url($value);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])
            || preg_match('/^\d+$/', (string) ($parts['host'] ?? ''))
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment']) || preg_match('/\s/', (string) $parts['host'])) return '';
        if (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) return '';
        $url = esc_url_raw($value, ['http', 'https']);
        if ($url === '') return '';
        $validated = wp_parse_url($url);
        if (!is_array($validated) || empty($validated['host'])) return '';
        return untrailingslashit($url);
    }
    public function all() {
        $settings = get_option(self::OPT, []);
        if (!is_array($settings) || !isset($settings['stations']) || !is_array($settings['stations'])) return [];
        $stations = [];
        foreach ($settings['stations'] as $row) {
            if (!is_array($row)) continue;
            $raw_shortcode = $row['shortcode'] ?? '';
            if (!is_scalar($raw_shortcode)) continue;
            $shortcode = sanitize_title((string) $raw_shortcode);
            $server = self::url($row['url'] ?? '');
            if (!$shortcode || !$server || isset($stations[$shortcode])) continue;
            $raw_name = $row['name'] ?? '';
            $name = is_scalar($raw_name) ? sanitize_text_field((string) $raw_name) : '';
            $raw_theme = $row['theme'] ?? 'dark';
            $theme = is_string($raw_theme) && in_array($raw_theme, ['dark', 'light'], true) ? $raw_theme : 'dark';
            $raw_css = $row['css'] ?? '';
            $css = is_scalar($raw_css) ? sanitize_textarea_field((string) $raw_css) : '';
            $stations[$shortcode] = [
                'name' => ($name !== '') ? $name : $shortcode,
                'url' => $server, 'shortcode' => $shortcode, 'theme' => $theme,
                'css' => $css, 'art' => !empty($row['art']),
            ];
        }
        return $stations;
    }
    public function get($identifier = '') {
        $all = $this->all();
        if (!$all) return null;
        $identifier = is_scalar($identifier) ? sanitize_title((string) $identifier) : '';
        if ($identifier !== '' && isset($all[$identifier])) return $all[$identifier];
        if ($identifier !== '') return null;
        $settings = get_option(self::OPT, []);
        $raw_default = is_array($settings) ? ($settings['default'] ?? '') : '';
        $default = is_scalar($raw_default) ? sanitize_title((string) $raw_default) : '';
        if (isset($all[$default])) return $all[$default];
        return reset($all) ?: null;
    }
}
