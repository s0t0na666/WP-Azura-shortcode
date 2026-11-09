<?php
if (!defined('ABSPATH')) { exit; }

final class AZNS_Admin {
    private $stations;
    private $api;

    public function __construct($stations, $api) {
        $this->stations = $stations;
        $this->api = $api;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_azns_save', [$this, 'save']);
        add_action('admin_post_azns_clear', [$this, 'clear']);
        add_action('admin_post_azns_export_diagnostics', [$this, 'export_diagnostics']);
        add_action('wp_ajax_azns_test_connection', [$this, 'ajax_test_connection']);
    }

    public function menu() {
        add_menu_page('AzuraCast', 'AzuraCast', 'manage_options', 'azns', [$this, 'page'], 'dashicons-controls-volumeon');
        add_submenu_page('azns', 'Logs & Diagnostics', 'Logs & Diagnostics', 'manage_options', 'azns-logs', [$this, 'logs']);
    }

    public function save() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('azns_save');

        $posted = wp_unslash($_POST);
        $shortcodes = (isset($posted['shortcode']) && is_array($posted['shortcode'])) ? $posted['shortcode'] : [];
        $fields = ['name', 'url', 'theme', 'css', 'art'];
        $submitted = [];
        foreach ($fields as $field) {
            $submitted[$field] = (isset($posted[$field]) && is_array($posted[$field])) ? $posted[$field] : [];
        }

        $rows = [];
        $seen = [];
        $invalid_shortcode_count = 0;
        $duplicate_shortcode_count = 0;
        $invalid_url_count = 0;

        foreach ($shortcodes as $index => $raw_shortcode) {
            if (!is_scalar($raw_shortcode)) {
                $invalid_shortcode_count++;
                continue;
            }
            $shortcode = sanitize_title((string) $raw_shortcode);
            if ($shortcode === '') {
                $invalid_shortcode_count++;
                continue;
            }
            if (isset($seen[$shortcode])) {
                $duplicate_shortcode_count++;
                continue;
            }

            $row = ['shortcode' => $shortcode];
            foreach ($fields as $field) {
                $value = $submitted[$field][$index] ?? '';
                if (!is_scalar($value)) $value = '';
                $value = (string) $value;
                $row[$field] = match ($field) {
                    'url' => AZNS_Stations::url($value),
                    'css' => sanitize_textarea_field($value),
                    'art' => ($value !== ''),
                    'theme' => in_array($value, ['dark', 'light'], true) ? $value : 'dark',
                    default => sanitize_text_field($value),
                };
            }

            if ($row['url'] === '') {
                $invalid_url_count++;
                continue;
            }

            $seen[$shortcode] = true;
            $rows[] = $row;
        }

        if (!$rows) {
            wp_safe_redirect(admin_url('admin.php?page=azns&error=no_stations'));
            exit;
        }

        $raw_default = $posted['default'] ?? '';
        $requested_default = is_scalar($raw_default) ? sanitize_title((string) $raw_default) : '';
        $valid = array_column($rows, 'shortcode');
        $default_corrected = !in_array($requested_default, $valid, true);
        $default = $default_corrected ? $valid[0] : $requested_default;

        update_option(AZNS_Stations::OPT, ['default' => $default, 'stations' => $rows], false);

        // Keep health history only for stations that remain configured.
        $health = get_option('azns_station_health', []);
        if (!is_array($health)) $health = [];
        update_option('azns_station_health', array_intersect_key($health, array_flip($valid)), false);

        AZNS_Logger::log('info', 'Settings saved', ['count' => count($rows)]);
        $redirect_args = ['saved' => 1];
        if ($invalid_shortcode_count > 0) $redirect_args['invalid_shortcode'] = $invalid_shortcode_count;
        if ($duplicate_shortcode_count > 0) $redirect_args['duplicate'] = $duplicate_shortcode_count;
        if ($invalid_url_count > 0) $redirect_args['invalid_url'] = $invalid_url_count;
        if ($default_corrected) $redirect_args['default_corrected'] = 1;

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php?page=azns')));
        exit;
    }

    public function page() {
        if (!current_user_can('manage_options')) { return; }
        $options = get_option(AZNS_Stations::OPT, []);
        if (!is_array($options)) $options = [];
        $rows = array_values($this->stations->all());
        if (!$rows) {
            $rows = [['shortcode' => '', 'name' => '', 'url' => '', 'theme' => 'dark', 'css' => '', 'art' => 1]];
        }
        $health = get_option('azns_station_health', []);
        if (!is_array($health)) $health = [];
        $invalid_shortcode_count = isset($_GET['invalid_shortcode']) ? absint(wp_unslash($_GET['invalid_shortcode'])) : 0;
        $duplicate_shortcode_count = isset($_GET['duplicate']) ? absint(wp_unslash($_GET['duplicate'])) : 0;
        $invalid_url_count = isset($_GET['invalid_url']) ? absint(wp_unslash($_GET['invalid_url'])) : 0;
        $default_corrected = isset($_GET['default_corrected']) && (string) wp_unslash($_GET['default_corrected']) === '1';
        ?>
        <div class="wrap">
            <h1>AzuraCast Suite</h1>
            <p>The player, history, podcasts and schedule are embedded from AzuraCast's public pages. The station shortcode is the station identifier used in WordPress shortcodes.</p>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'no_stations') : ?><div class="notice notice-error"><p>No valid station was saved. Add at least one station with a valid HTTP or HTTPS server URL.</p></div><?php endif; ?>
            <?php if ($invalid_shortcode_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($invalid_shortcode_count . ' station row(s) were skipped because the shortcode was empty or invalid.'); ?></p></div><?php endif; ?>
            <?php if ($duplicate_shortcode_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($duplicate_shortcode_count . ' duplicate station shortcode(s) were skipped. Each shortcode must be unique.'); ?></p></div><?php endif; ?>
            <?php if ($invalid_url_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($invalid_url_count . ' station row(s) were skipped because the server URL was invalid. Use an HTTP or HTTPS URL without credentials, query strings or fragments.'); ?></p></div><?php endif; ?>
            <?php if ($default_corrected) : ?><div class="notice notice-info"><p>The selected default station was not valid, so the first saved station was selected instead.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="azns_save"><?php wp_nonce_field('azns_save'); ?>
                <table class="widefat striped">
                    <thead><tr>
                        <?php foreach (['Station shortcode', 'Display name', 'AzuraCast server URL', 'AzuraCast public-page theme', 'Show artwork', 'Custom CSS for WordPress components', 'Connection', ''] as $heading) { echo '<th>' . esc_html($heading) . '</th>'; } ?>
                    </tr></thead>
                    <tbody id="azns-rows" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-test-nonce="<?php echo esc_attr(wp_create_nonce('azns_test_connection')); ?>"><?php foreach ($rows as $index => $row) { $this->row($index, $row, $health); } ?></tbody>
                </table>
                <p><button type="button" class="button" id="azns-add">Add station</button></p>
                <?php
                $raw_default = $options['default'] ?? '';
                $default_value = is_scalar($raw_default) ? sanitize_title((string) $raw_default) : '';
                if (!$default_value && !empty($rows[0]['shortcode'])) $default_value = $rows[0]['shortcode'];
                ?>
                <p>Default station: <select name="default">
                    <?php foreach ($rows as $row) {
                        $sc = sanitize_title((string) ($row['shortcode'] ?? ''));
                        $name = (string) ($row['name'] ?? $sc);
                        echo '<option value="' . esc_attr($sc) . '" ' . selected($default_value, $sc, false) . '>' . esc_html($name . ' (' . $sc . ')') . '</option>';
                    } ?>
                </select></p>
                <?php submit_button('Save settings'); ?>
            </form>
            <h2>Shortcodes</h2>
            <p><code>[azura_player station="your_station_shortcode"]</code> <code>[azura_nowplaying station="your_station_shortcode"]</code> <code>[azura_next_track station="your_station_shortcode"]</code> <code>[azura_history station="your_station_shortcode" limit="10"]</code> <code>[azura_podcasts station="your_station_shortcode"]</code> <code>[azura_schedule station="your_station_shortcode"]</code></p>
            <p>The four public-page shortcodes embed AzuraCast's own pages: player <code>/public/{station}/embed?theme={theme}</code> (150px), history <code>/public/{station}/history?theme={theme}</code> (300px), podcasts <code>/public/{station}/podcasts?embed=true&amp;theme={theme}</code> (400px), and schedule <code>/public/{station}/schedule/embed?theme={theme}</code> (800px). These dimensions match the supplied embed snippets. The selected theme is passed to AzuraCast; WordPress CSS does not style the contents of these iframes.</p>
            <p>The current components use public AzuraCast endpoints. The supplied 0.24.0 OpenAPI schema accepts a station shortcode as a string station identifier, so a separate numeric ID and API key are not required.</p>
            <p>Legacy aliases: <code>[azuracast_playing_next]</code>, <code>[azura_playing_next]</code>.</p>
        </div>
        <script>
        (function () {
            const addButton = document.getElementById('azns-add');
            const rows = document.getElementById('azns-rows');
            if (!addButton || !rows) return;

            const template = <?php ob_start(); $this->row('__I__', ['shortcode'=>'','name'=>'','url'=>'','theme'=>'dark','css'=>'','art'=>1], []); $template=ob_get_clean(); echo wp_json_encode($template); ?>;
            addButton.addEventListener('click', function () {
                rows.insertAdjacentHTML('beforeend', template.replaceAll('__I__', String(Date.now())));
            });

            rows.addEventListener('click', async function (event) {
                const removeButton = event.target.closest('.azns-remove');
                if (removeButton) {
                    removeButton.closest('tr').remove();
                    return;
                }

                const testButton = event.target.closest('.azns-test-connection');
                if (!testButton) return;

                const row = testButton.closest('tr');
                const result = row.querySelector('.azns-test-result');
                const health = row.querySelector('.azns-health');
                const shortcodeInput = row.querySelector('input[name^="shortcode["]');
                const shortcode = shortcodeInput ? shortcodeInput.value.trim() : '';
                if (!shortcode) {
                    result.textContent = 'Enter a station shortcode first.';
                    return;
                }

                testButton.disabled = true;
                result.textContent = 'Testing connection…';
                const form = new FormData();
                form.append('action', 'azns_test_connection');
                form.append('nonce', rows.dataset.testNonce || '');
                form.append('shortcode', shortcode);

                try {
                    const response = await fetch(rows.dataset.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: form,
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        if (health) health.textContent = 'Unreachable — checked just now';
                        throw new Error(payload.data && payload.data.message ? payload.data.message : 'Connection test failed.');
                    }
                    if (health) health.textContent = 'Reachable — checked just now';
                    const stationName = payload.data.station_name ? ' Station: ' + payload.data.station_name + '.' : '';
                    result.textContent = payload.data.message + stationName;
                } catch (error) {
                    result.textContent = error.message || 'Connection test failed.';
                } finally {
                    testButton.disabled = false;
                }
            });
        })();
        </script>
        <?php
    }

    private function row($index, $row, $health = []) {
        $row = is_array($row) ? $row : [];
        echo '<tr>';
        foreach (['shortcode', 'name', 'url'] as $field) {
            $raw_value = $row[$field] ?? '';
            $value = is_scalar($raw_value) ? (string) $raw_value : '';
            echo '<td><input style="width:100%;min-width:90px" type="text" name="' . esc_attr($field) . '[' . esc_attr($index) . ']" value="' . esc_attr($value) . '" autocomplete="new-password"></td>';
        }
        $raw_theme = $row['theme'] ?? 'dark';
        $theme = is_string($raw_theme) && in_array($raw_theme, ['dark', 'light'], true) ? $raw_theme : 'dark';
        echo '<td><select name="theme[' . esc_attr($index) . ']">';
        foreach (['dark' => 'Dark', 'light' => 'Light'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($theme, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
        echo '<td><input type="checkbox" name="art[' . esc_attr($index) . ']" value="1" ' . checked(!empty($row['art']), true, false) . '></td>';
        $raw_css = $row['css'] ?? '';
        $css = is_scalar($raw_css) ? (string) $raw_css : '';
        echo '<td><textarea name="css[' . esc_attr($index) . ']" rows="2" style="width:120px">' . esc_textarea($css) . '</textarea></td>';
        $shortcode = sanitize_title((string) ($row['shortcode'] ?? ''));
        $station_health = (isset($health[$shortcode]) && is_array($health[$shortcode])) ? $health[$shortcode] : [];
        echo '<td><span class="azns-health" role="status" aria-live="polite">' . esc_html($this->health_label($station_health)) . '</span><br>';
        echo '<button type="button" class="button azns-test-connection">Test connection</button> <span class="azns-test-result"></span></td>';
        echo '<td><button type="button" class="button-link-delete azns-remove">Remove</button></td></tr>';
    }

    private function health_label($health) {
        $status = is_array($health) ? ($health['status'] ?? '') : '';
        $checked_at = is_array($health) ? absint($health['checked_at'] ?? 0) : 0;
        $last_successful_at = is_array($health) ? absint($health['last_successful_at'] ?? 0) : 0;
        $station_name = is_array($health) && is_scalar($health['station_name'] ?? '') ? sanitize_text_field((string) $health['station_name']) : '';

        if ($status === 'ok') {
            $label = 'Reachable';
            if ($station_name !== '') $label .= ': ' . $station_name;
            if ($checked_at > 0) $label .= ' — checked ' . $this->format_health_time($checked_at);
            return $label;
        }

        if ($status === 'error') {
            $label = 'Unreachable';
            if ($checked_at > 0) $label .= ' — checked ' . $this->format_health_time($checked_at);
            if ($last_successful_at > 0) $label .= '; last success ' . $this->format_health_time($last_successful_at);
            return $label;
        }

        return 'Not tested';
    }

    private function format_health_time($timestamp) {
        $format = trim((string) get_option('date_format', 'Y-m-d') . ' ' . (string) get_option('time_format', 'H:i'));
        if (function_exists('wp_date')) return wp_date($format, $timestamp);
        return date_i18n($format, $timestamp);
    }

    private function update_station_health($shortcode, $status, $http_status = 0, $station_name = '') {
        $health = get_option('azns_station_health', []);
        if (!is_array($health)) $health = [];
        $previous = (isset($health[$shortcode]) && is_array($health[$shortcode])) ? $health[$shortcode] : [];
        $now = time();

        $entry = [
            'status' => ($status === 'ok') ? 'ok' : 'error',
            'checked_at' => $now,
            'http_status' => max(0, (int) $http_status),
            'last_successful_at' => ($status === 'ok') ? $now : absint($previous['last_successful_at'] ?? 0),
            'station_name' => ($status === 'ok')
                ? (is_scalar($station_name) ? sanitize_text_field((string) $station_name) : '')
                : (is_scalar($previous['station_name'] ?? '') ? sanitize_text_field((string) $previous['station_name']) : ''),
        ];

        $health[sanitize_title((string) $shortcode)] = $entry;
        update_option('azns_station_health', $health, false);
    }

    public function ajax_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to test station connections.'], 403);
        }
        check_ajax_referer('azns_test_connection', 'nonce');

        $raw_shortcode = isset($_POST['shortcode']) ? wp_unslash($_POST['shortcode']) : '';
        if (!is_scalar($raw_shortcode)) {
            wp_send_json_error(['message' => 'A valid station shortcode is required.'], 400);
        }
        $shortcode = sanitize_title((string) $raw_shortcode);
        $station = $this->stations->get($shortcode);
        if (!$station) {
            wp_send_json_error(['message' => 'Station not found. Save the station settings before testing the connection.'], 404);
        }

        $endpoint = trailingslashit($station['url']) . 'api/nowplaying/' . rawurlencode($station['shortcode']);
        $response = wp_remote_get($endpoint, [
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->update_station_health($shortcode, 'error');
            AZNS_Logger::log('warning', 'Station connection test failed', [
                'station' => $station['shortcode'],
                'error' => $response->get_error_message(),
            ]);
            wp_send_json_error(['message' => 'Connection failed: ' . $response->get_error_message()], 502);
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300) {
            $this->update_station_health($shortcode, 'error', $status);
            AZNS_Logger::log('warning', 'Station connection test returned HTTP error', [
                'station' => $station['shortcode'],
                'http' => $status,
            ]);
            wp_send_json_error(['message' => 'AzuraCast returned HTTP ' . (int) $status . '. Check the server URL and public station endpoint.'], 502);
        }
        if (!is_array($data) || !isset($data['station']) || !is_array($data['station'])) {
            $this->update_station_health($shortcode, 'error', $status);
            AZNS_Logger::log('warning', 'Station connection test returned invalid data', [
                'station' => $station['shortcode'],
                'http' => $status,
            ]);
            wp_send_json_error(['message' => 'The server responded, but did not return valid AzuraCast Now Playing data.'], 502);
        }

        $remote_name = $data['station']['name'] ?? '';
        $remote_name = is_scalar($remote_name) ? sanitize_text_field((string) $remote_name) : '';
        $this->update_station_health($shortcode, 'ok', $status, $remote_name);
        AZNS_Logger::log('info', 'Station connection test succeeded', [
            'station' => $station['shortcode'],
            'http' => $status,
        ]);

        wp_send_json_success([
            'message' => 'Connection successful (HTTP ' . (int) $status . ').',
            'station_name' => $remote_name,
        ]);
    }

    public function diagnostics_report() {
        $health = get_option('azns_station_health', []);
        if (!is_array($health)) $health = [];

        $station_rows = [];
        foreach ($this->stations->all() as $station) {
            $shortcode = $station['shortcode'];
            $record = (isset($health[$shortcode]) && is_array($health[$shortcode])) ? $health[$shortcode] : [];
            $station_rows[] = [
                'shortcode' => $shortcode,
                'configured' => true,
                'theme' => in_array($station['theme'], ['dark', 'light'], true) ? $station['theme'] : 'dark',
                'artwork_enabled' => !empty($station['art']),
                'connection' => [
                    'status' => in_array($record['status'] ?? '', ['ok', 'error'], true) ? $record['status'] : 'not_tested',
                    'checked_at' => absint($record['checked_at'] ?? 0),
                    'last_successful_at' => absint($record['last_successful_at'] ?? 0),
                    'http_status' => absint($record['http_status'] ?? 0),
                    'station_name' => is_scalar($record['station_name'] ?? '') ? sanitize_text_field((string) $record['station_name']) : '',
                ],
            ];
        }

        return [
            'generated_at' => gmdate('c'),
            'plugin' => ['name' => 'AzuraCast Suite', 'version' => AZNS_VER],
            'environment' => [
                'wordpress_version' => sanitize_text_field((string) get_bloginfo('version')),
                'php_version' => PHP_VERSION,
                'multisite' => is_multisite(),
            ],
            'stations' => $station_rows,
            'logs' => AZNS_Logger::export_rows(),
            'privacy' => [
                'server_urls' => 'omitted',
                'credentials_and_tokens' => 'redacted',
                'custom_css' => 'omitted',
            ],
        ];
    }

    public function export_diagnostics() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('azns_export_diagnostics');

        $report = $this->diagnostics_report();
        $filename = 'azuracast-diagnostics-' . gmdate('Ymd-His') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function logs() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>AzuraCast logs</h1><p>Stored in uploads/azuracast-logs. Apache/LiteSpeed and IIS protection files are created automatically. Nginx does not read .htaccess files; add a deny rule for your actual uploads URL path. With the default WordPress uploads path:</p><pre><code>location ^~ /wp-content/uploads/azuracast-logs/ { deny all; }</code></pre>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="azns_export_diagnostics">';
        wp_nonce_field('azns_export_diagnostics');
        submit_button('Download sanitized diagnostics report', 'secondary');
        echo '</form><h2>Recent log entries</h2><pre style="max-height:500px;overflow:auto;background:white;padding:12px">' . esc_html(AZNS_Logger::tail()) . '</pre><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="azns_clear">';
        wp_nonce_field('azns_clear');
        submit_button('Clear logs', 'secondary');
        echo '</form></div>';
    }

    public function clear() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('azns_clear');
        AZNS_Logger::clear();
        wp_safe_redirect(admin_url('admin.php?page=azns-logs'));
        exit;
    }
}
