<?php
if (!defined('ABSPATH')) { exit; }
final class AZNS_Logger {
    public static function dir() {
        $uploads = wp_upload_dir();
        $base = (is_array($uploads) && !empty($uploads['basedir'])) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
        return trailingslashit($base) . 'azuracast-logs';
    }
    public static function init() {
        $dir = self::dir();
        if (!wp_mkdir_p($dir)) return;
        $index = "<?php\n// Silence is golden.\n";
        if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', $index, LOCK_EX);
        $htaccess = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order Allow,Deny\n    Deny from all\n</IfModule>\n";
        $htaccess_path = $dir . '/.htaccess';
        if (@file_get_contents($htaccess_path) !== $htaccess) @file_put_contents($htaccess_path, $htaccess, LOCK_EX);
        $web_config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n"
            . "    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n"
            . "        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n"
            . "  </system.webServer>\n</configuration>\n";
        $web_config_path = $dir . '/web.config';
        if (@file_get_contents($web_config_path) !== $web_config) @file_put_contents($web_config_path, $web_config, LOCK_EX);
    }
    private static function redact_string($value) {
        $value = (string) $value;
        // Remove complete URLs because even a hostname or path can identify a private station.
        $value = preg_replace('~https?://[^[:space:]<>]+~i', '[url omitted]', $value);
        $value = preg_replace('~([?&](?:api[_-]?key|access[_-]?token|authorization|password|secret|signature|token)=)[^&\\s#]+~i', '$1[redacted]', $value);
        $value = preg_replace('/\\b(Bearer)\\s+[A-Za-z0-9._~+\\/-]+=*/i', '$1 [redacted]', $value);
        return sanitize_text_field($value);
    }

    private static function clean_context($context) {
        if (!is_array($context)) return [];
        $clean = [];
        foreach ($context as $key => $value) {
            $key_string = (string) $key;
            if (preg_match('/(api[_-]?key|authorization|password|secret|token|cookie|private[_-]?key)/i', $key_string)) {
                $clean[$key_string] = '[redacted]';
            } elseif (is_array($value)) {
                $clean[$key_string] = self::clean_context($value);
            } elseif (is_string($value)) {
                $clean[$key_string] = self::redact_string($value);
            } elseif (is_scalar($value)) {
                $clean[$key_string] = $value;
            }
        }
        return $clean;
    }

    /**
     * Return a sanitized copy of recent log rows for administrator diagnostics.
     * Raw log file contents and filesystem paths are never exported.
     */
    public static function export_rows() {
        $tail = self::tail();
        if ($tail === '') return [];

        $rows = [];
        foreach (preg_split('/\\r?\\n/', $tail) as $line) {
            if (!is_string($line) || trim($line) === '') continue;
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = self::clean_context($decoded);
            } else {
                $rows[] = ['message' => self::redact_string($line)];
            }
        }
        return $rows;
    }

    public static function log($level, $message, $context = []) {
        self::init();
        $dir = self::dir();
        if (!is_dir($dir) || !is_writable($dir)) return;
        $row = ['time' => gmdate('c'), 'level' => sanitize_key($level),
            'message' => self::redact_string($message), 'context' => self::clean_context($context)];
        $encoded = wp_json_encode($row);
        if (is_string($encoded)) @file_put_contents($dir . '/azuracast.log', $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    public static function tail() {
        $file = self::dir() . '/azuracast.log';
        if (!is_readable($file)) return '';
        $handle = @fopen($file, 'rb');
        if (!$handle) return '';
        $position = @filesize($file);
        if (!is_int($position) || $position < 0) { fclose($handle); return ''; }
        $buffer = '';
        while ($position > 0 && substr_count($buffer, "\n") <= 150) {
            $length = min(4096, $position);
            $position -= $length;
            if (fseek($handle, $position) !== 0) break;
            $chunk = fread($handle, $length);
            if ($chunk === false) break;
            $buffer = $chunk . $buffer;
        }
        fclose($handle);
        $lines = preg_split('/\r?\n/', trim($buffer));
        return implode("\n", array_slice($lines ?: [], -150));
    }
    public static function clear() {
        $file = self::dir() . '/azuracast.log';
        if (is_file($file)) @unlink($file);
    }
}
