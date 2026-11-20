<?php
if (!defined('ABSPATH')) { exit; }
final class AZNS_Logger {
    private const MAX_LOG_BYTES = 1048576;
    private const MAX_LINES = 150;

    public static function dir() {
        if (defined('AZNS_LOG_DIR') && is_string(AZNS_LOG_DIR) && trim(AZNS_LOG_DIR) !== '') {
            return untrailingslashit(AZNS_LOG_DIR);
        }

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
        if (!is_string($encoded)) return;

        $line = $encoded . PHP_EOL;
        if (strlen($line) > self::MAX_LOG_BYTES) return;

        self::with_log_lock($dir, LOCK_EX, static function ($file) use ($line) {
            clearstatcache(true, $file);
            $size = is_file($file) ? @filesize($file) : 0;
            if ($size === false) return;

            if ($size + strlen($line) > self::MAX_LOG_BYTES) {
                if ($size > self::MAX_LOG_BYTES) {
                    // Retain only the recent, complete lines from a pre-upgrade log.
                    $recent = self::read_tail($file);
                    if (@file_put_contents($file . '.1', rtrim($recent, "\r\n") . PHP_EOL) === false || !@unlink($file)) return;
                } elseif (!@rename($file, $file . '.1')) {
                    return;
                }
            }

            @file_put_contents($file, $line, FILE_APPEND);
        });
    }

    public static function tail() {
        return self::with_log_lock(self::dir(), LOCK_SH, static function ($file) {
            $current = self::read_tail($file);
            $lines = $current === '' ? [] : explode("\n", $current);
            if (count($lines) < self::MAX_LINES) {
                $previous = self::read_tail($file . '.1');
                if ($previous !== '') $lines = array_merge(explode("\n", $previous), $lines);
            }
            return implode("\n", array_slice($lines, -self::MAX_LINES));
        }) ?? '';
    }

    private static function read_tail($file) {
        $handle = @fopen($file, 'rb');
        if (!$handle) return '';

        $position = fstat($handle)['size'] ?? 0;
        $minimum = max(0, $position - self::MAX_LOG_BYTES);
        $buffer = '';
        while ($position > $minimum && substr_count($buffer, "\n") <= self::MAX_LINES) {
            $length = min(4096, $position - $minimum);
            $position -= $length;
            if (fseek($handle, $position) !== 0) break;
            $chunk = fread($handle, $length);
            if ($chunk === false) break;
            $buffer = $chunk . $buffer;
        }
        fclose($handle);

        if ($position > 0) {
            $boundary = strpos($buffer, "\n");
            $buffer = $boundary === false ? '' : substr($buffer, $boundary + 1);
        }

        $lines = preg_split('/\r?\n/', rtrim($buffer, "\r\n"));
        return implode("\n", array_slice($lines ?: [], -self::MAX_LINES));
    }

    private static function with_log_lock($dir, $mode, $callback) {
        if (!is_dir($dir)) return null;
        $lock = @fopen($dir . '/azuracast.lock', 'c');
        if (!$lock) return null;

        try {
            if (!flock($lock, $mode)) return null;
            return $callback($dir . '/azuracast.log');
        } finally {
            fclose($lock);
        }
    }

    public static function clear() {
        self::with_log_lock(self::dir(), LOCK_EX, static function ($file) {
            if (is_file($file)) @unlink($file);
            if (is_file($file . '.1')) @unlink($file . '.1');
        });
    }
}
