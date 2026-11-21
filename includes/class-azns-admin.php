<?php
if (!defined('ABSPATH')) { exit; }

final class AZNS_Admin {
    private $stations;
    private $screens = [];

    public function __construct($stations) {
        $this->stations = $stations;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_azns_save', [$this, 'save']);
        add_action('admin_post_azns_clear', [$this, 'clear']);
        add_action('admin_post_azns_export_diagnostics', [$this, 'export_diagnostics']);
    }

    public function menu() {
        $this->screens[] = add_menu_page('AzuraCast Suite', 'AzuraCast', 'manage_options', 'azns', [$this, 'page'], 'dashicons-controls-volumeon');
        add_submenu_page('azns', 'Stations', 'Stations', 'manage_options', 'azns', [$this, 'page']);
        $this->screens[] = add_submenu_page('azns', 'Shortcodes', 'Shortcodes', 'manage_options', 'azns-shortcodes', [$this, 'shortcodes']);
        $this->screens[] = add_submenu_page('azns', 'Logs & Diagnostics', 'Logs & Diagnostics', 'manage_options', 'azns-logs', [$this, 'logs']);
    }

    public function enqueue($screen) {
        if (!in_array($screen, $this->screens, true)) return;
        wp_enqueue_style('azns-admin', AZNS_URL . 'assets/css/admin.css', [], AZNS_VER);
        wp_enqueue_script('azns-admin', AZNS_URL . 'assets/js/admin.js', [], AZNS_VER, true);
    }

    public function save() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('azns_save');

        $posted = wp_unslash($_POST);
        $shortcodes = (isset($posted['shortcode']) && is_array($posted['shortcode'])) ? $posted['shortcode'] : [];
        $fields = ['name', 'url', 'theme', 'css', 'art'];
        $labels = [];
        foreach (array_keys(AZNS_Stations::MODE_LABELS) as $field) {
            $labels[$field] = isset($posted[$field]) && is_array($posted[$field]) ? $posted[$field] : [];
        }
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
            $rows[] = $row + AZNS_Stations::mode_labels(array_map(
                static fn ($values) => $values[$index] ?? '',
                $labels
            ));
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
        $rows = $this->stations->all(false);
        $default = $this->stations->get()['shortcode'] ?? '';
        $blank = ['shortcode' => '', 'name' => '', 'url' => '', 'theme' => 'dark', 'css' => '', 'art' => true];
        if (!$rows) $rows = [$blank];
        $invalid_shortcode_count = isset($_GET['invalid_shortcode']) ? absint(wp_unslash($_GET['invalid_shortcode'])) : 0;
        $duplicate_shortcode_count = isset($_GET['duplicate']) ? absint(wp_unslash($_GET['duplicate'])) : 0;
        $invalid_url_count = isset($_GET['invalid_url']) ? absint(wp_unslash($_GET['invalid_url'])) : 0;
        $default_corrected = isset($_GET['default_corrected']) && (string) wp_unslash($_GET['default_corrected']) === '1';
        ?>
        <div class="wrap azns-admin">
            <div class="azns-admin-heading">
                <div><h1>AzuraCast Suite: Stations</h1><p>Manage the stations available to your shortcodes and blocks.</p></div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=azns-shortcodes')); ?>">View shortcodes</a>
            </div>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'no_stations') : ?><div class="notice notice-error"><p>No valid station was saved. Add at least one station with a valid HTTP or HTTPS server URL.</p></div><?php endif; ?>
            <?php if ($invalid_shortcode_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($invalid_shortcode_count . ' station row(s) were skipped because the shortcode was empty or invalid.'); ?></p></div><?php endif; ?>
            <?php if ($duplicate_shortcode_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($duplicate_shortcode_count . ' duplicate station shortcode(s) were skipped. Each shortcode must be unique.'); ?></p></div><?php endif; ?>
            <?php if ($invalid_url_count > 0) : ?><div class="notice notice-warning"><p><?php echo esc_html($invalid_url_count . ' station row(s) were skipped because the server URL was invalid. Use an HTTP or HTTPS URL without credentials, query strings or fragments.'); ?></p></div><?php endif; ?>
            <?php if ($default_corrected) : ?><div class="notice notice-info"><p>The selected default station was not valid, so the first saved station was selected instead.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="azns_save"><?php wp_nonce_field('azns_save'); ?>
                <section class="azns-admin-panel azns-admin-default" aria-labelledby="azns-default-title">
                    <h2 id="azns-default-title">Playback defaults</h2>
                    <label for="azns-default">Default station</label>
                    <select id="azns-default" name="default">
                        <?php foreach ($rows as $row) :
                            if ($row['shortcode'] === '') continue;
                            $label = $row['name'] !== '' ? $row['name'] . ' (' . $row['shortcode'] . ')' : $row['shortcode'];
                            ?>
                            <option value="<?php echo esc_attr($row['shortcode']); ?>" <?php selected($default, $row['shortcode']); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Used by shortcodes and blocks that leave the station unspecified.</p>
                </section>

                <div class="azns-admin-section-heading">
                    <h2>Configured stations</h2>
                    <button type="button" class="button" id="azns-add" hidden>Add station</button>
                </div>
                <div id="azns-rows">
                    <?php foreach (array_values($rows) as $index => $row) { $this->station_card($index, $row, $default); } ?>
                </div>
                <template id="azns-station-template"><?php $this->station_card('__INDEX__', $blank, ''); ?></template>
                <div class="azns-admin-actions">
                    <?php submit_button('Save settings', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    private function station_card($index, $row, $default) {
        $title = $row['name'] !== '' ? $row['name'] : $row['shortcode'];
        $is_default = $default !== '' && $row['shortcode'] === $default;
        ?>
        <section class="azns-admin-panel azns-admin-station" aria-labelledby="azns-station-title-<?php echo esc_attr($index); ?>">
            <div class="azns-admin-section-heading">
                <h3 id="azns-station-title-<?php echo esc_attr($index); ?>" data-station-title><?php echo esc_html($title !== '' ? $title : 'New station'); ?></h3>
                <span class="azns-admin-badge" data-station-default <?php if (!$is_default) echo 'hidden'; ?>>Default</span>
            </div>
            <div class="azns-admin-fields">
                <label>Display name
                    <input type="text" name="name[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($row['name']); ?>" data-station-name>
                </label>
                <label>Station identifier
                    <input type="text" name="shortcode[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($row['shortcode']); ?>" data-station-slug>
                </label>
                <label class="azns-admin-wide">AzuraCast server URL
                    <input type="text" inputmode="url" name="url[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($row['url']); ?>" placeholder="https://radio.example.com">
                </label>
            </div>
            <details class="azns-admin-appearance">
                <summary>Appearance</summary>
                <div class="azns-admin-fields">
                    <label>Public page theme
                        <select name="theme[<?php echo esc_attr($index); ?>]">
                            <option value="dark" <?php selected($row['theme'], 'dark'); ?>>Dark</option>
                            <option value="light" <?php selected($row['theme'], 'light'); ?>>Light</option>
                        </select>
                    </label>
                    <label class="azns-admin-checkbox"><input type="checkbox" name="art[<?php echo esc_attr($index); ?>]" value="1" <?php checked($row['art']); ?>> Show artwork</label>
                    <label class="azns-admin-wide">Custom CSS
                        <textarea name="css[<?php echo esc_attr($index); ?>]" rows="4"><?php echo esc_textarea($row['css']); ?></textarea>
                    </label>
                </div>
            </details>
            <details class="azns-admin-appearance">
                <summary>Broadcast Mode labels</summary>
                <p class="description">Used by the Broadcast Mode shortcode and block. Clear a label to restore its default.</p>
                <div class="azns-admin-fields">
                    <?php foreach (AZNS_Stations::MODE_LABELS as $field => $default_label) : ?>
                        <label><?php echo esc_html($default_label . ' label'); ?>
                            <input type="text" name="<?php echo esc_attr($field . '[' . $index . ']'); ?>" value="<?php echo esc_attr($row[$field] ?? ''); ?>" placeholder="<?php echo esc_attr($default_label); ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
            </details>
            <div class="azns-admin-station-footer"><button type="button" class="button-link-delete azns-remove" hidden>Remove station</button></div>
        </section>
        <?php
    }

    public function shortcodes() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap azns-admin">
            <div class="azns-admin-heading">
                <div><h1>AzuraCast Suite: Shortcodes</h1><p>Copy a shortcode into a page, post or supported page builder.</p></div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=azns')); ?>">Manage stations</a>
            </div>
            <div class="azns-admin-panel azns-admin-default">
                <label for="azns-shortcode-station">Station for shortcode examples</label>
                <select id="azns-shortcode-station">
                    <option value="">Default station</option>
                    <?php foreach ($this->stations->all() as $station) : ?>
                        <option value="<?php echo esc_attr($station['shortcode']); ?>"><?php echo esc_html($station['name'] . ' (' . $station['shortcode'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Default station examples follow the station selected in Settings.</p>
                <p class="screen-reader-text" id="azns-copy-status" role="status" aria-atomic="true"></p>
            </div>
            <?php foreach (AZNS_Shortcodes::CATALOG as $group => $items) : ?>
                <section class="azns-admin-panel azns-admin-catalog">
                    <h2><?php echo esc_html($group); ?></h2>
                    <?php foreach ($items as $tag => [$name, $description]) : ?>
                        <div class="azns-admin-shortcode">
                            <div><h3><?php echo esc_html($name); ?></h3><p><?php echo esc_html($description); ?></p></div>
                            <div class="azns-admin-copy">
                                <code data-shortcode="<?php echo esc_attr($tag); ?>"><?php echo esc_html('[' . $tag . ']'); ?></code>
                                <button type="button" class="button" aria-label="<?php echo esc_attr('Copy ' . $name . ' shortcode'); ?>" data-copy-shortcode hidden>Copy</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
            <details class="azns-admin-panel">
                <summary>Legacy Playing Next aliases</summary>
                <ul>
                    <?php foreach (AZNS_Shortcodes::LEGACY_TAGS as $alias) : ?>
                        <li><code><?php echo esc_html('[' . $alias . ']'); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
        <?php
    }

    public function diagnostics_report() {
        $station_rows = [];
        foreach ($this->stations->all() as $station) {
            $shortcode = $station['shortcode'];
            $station_rows[] = [
                'shortcode' => $shortcode,
                'configured' => true,
                'theme' => in_array($station['theme'], ['dark', 'light'], true) ? $station['theme'] : 'dark',
                'artwork_enabled' => !empty($station['art']),
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
        $recent = AZNS_Logger::tail();
        ?>
        <div class="wrap azns-admin">
            <div class="azns-admin-heading">
                <div><h1>AzuraCast Suite: Logs &amp; Diagnostics</h1><p>Review recent events and export a report for troubleshooting.</p></div>
            </div>
            <section class="azns-admin-panel">
                <h2>Diagnostics report</h2>
                <p>Download a sanitized JSON report with plugin settings metadata and recent events.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="azns_export_diagnostics">
                    <?php wp_nonce_field('azns_export_diagnostics'); ?>
                    <?php submit_button('Download diagnostics', 'primary', 'azns-download', false); ?>
                </form>
            </section>
            <section class="azns-admin-panel">
                <h2>Recent log entries</h2>
                <?php if ($recent !== '') : ?>
                    <pre class="azns-admin-log" tabindex="0" aria-label="Recent log entries"><?php echo esc_html($recent); ?></pre>
                    <form class="azns-admin-clear" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="azns_clear">
                        <?php wp_nonce_field('azns_clear'); ?>
                        <label class="azns-admin-checkbox"><input type="checkbox" name="confirm_clear" value="1" required> Confirm deletion of the active and archived logs</label>
                        <?php submit_button('Clear logs', 'secondary', 'azns-clear-logs', false); ?>
                    </form>
                <?php else : ?>
                    <p>No log entries yet.</p>
                <?php endif; ?>
            </section>
            <details class="azns-admin-panel">
                <summary>Log storage</summary>
                <p>Diagnostics retain the current log and one archive, up to 1 MiB each.</p>
                <?php if (!defined('AZNS_LOG_DIR') || !is_string(AZNS_LOG_DIR) || trim(AZNS_LOG_DIR) === '') : ?>
                    <p>Default location: uploads/azuracast-logs. Set <code>AZNS_LOG_DIR</code> to an absolute writable directory outside the web root to change it. For the default location on Nginx, deny direct access to the actual uploads URL path:</p>
                    <pre class="azns-admin-log"><code>location ^~ /wp-content/uploads/azuracast-logs/ { deny all; }</code></pre>
                <?php endif; ?>
            </details>
        </div>
        <?php
    }

    public function clear() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('azns_clear');
        if (($_POST['confirm_clear'] ?? '') !== '1') {
            wp_safe_redirect(admin_url('admin.php?page=azns-logs'));
            exit;
        }
        AZNS_Logger::clear();
        wp_safe_redirect(admin_url('admin.php?page=azns-logs'));
        exit;
    }
}
