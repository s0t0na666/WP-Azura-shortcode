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
        foreach ($shortcodes as $index => $raw_shortcode) {
            if (!is_scalar($raw_shortcode)) continue;
            $shortcode = sanitize_title((string) $raw_shortcode);
            if (!$shortcode || isset($seen[$shortcode])) continue;
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
            if ($row['url'] === '') continue;
            $seen[$shortcode] = true;
            $rows[] = $row;
        }
        if (!$rows) {
            wp_safe_redirect(admin_url('admin.php?page=azns&error=no_stations'));
            exit;
        }
        $raw_default = $posted['default'] ?? '';
        $default = is_scalar($raw_default) ? sanitize_title((string) $raw_default) : '';
        $valid = array_column($rows, 'shortcode');
        if (!in_array($default, $valid, true)) $default = $valid[0];
        update_option(AZNS_Stations::OPT, ['default' => $default, 'stations' => $rows], false);
        AZNS_Logger::log('info', 'Settings saved', ['count' => count($rows)]);
        wp_safe_redirect(admin_url('admin.php?page=azns&saved=1'));
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
        ?>
        <div class="wrap">
            <h1>AzuraCast Suite</h1>
            <p>The player, history, podcasts and schedule are embedded from AzuraCast's public pages. The station shortcode is the station identifier used in WordPress shortcodes.</p>
            <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'no_stations') : ?><div class="notice notice-error"><p>No valid station was saved. Add at least one station with a valid HTTP or HTTPS server URL.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="azns_save"><?php wp_nonce_field('azns_save'); ?>
                <table class="widefat striped">
                    <thead><tr>
                        <?php foreach (['Station shortcode', 'Display name', 'AzuraCast server URL', 'AzuraCast public-page theme', 'Show artwork', 'Custom CSS for WordPress components', ''] as $heading) { echo '<th>' . esc_html($heading) . '</th>'; } ?>
                    </tr></thead>
                    <tbody id="azns-rows"><?php foreach ($rows as $index => $row) { $this->row($index, $row); } ?></tbody>
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
        <script>(function(){const b=document.getElementById('azns-add'),t=document.getElementById('azns-rows');if(!b||!t)return;const tpl=<?php ob_start(); $this->row('__I__', ['shortcode'=>'','name'=>'','url'=>'','theme'=>'dark','css'=>'','art'=>1]); $template=ob_get_clean(); echo wp_json_encode($template); ?>;b.addEventListener('click',()=>t.insertAdjacentHTML('beforeend',tpl.replaceAll('__I__',String(Date.now()))));t.addEventListener('click',e=>{if(e.target.classList.contains('azns-remove'))e.target.closest('tr').remove();});})();</script>
        <?php
    }

    private function row($index, $row) {
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
        echo '<td><button type="button" class="button-link-delete azns-remove">Remove</button></td></tr>';
    }

    public function logs() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>AzuraCast logs</h1><p>Stored in uploads/azuracast-logs. Apache/LiteSpeed and IIS protection files are created automatically. Nginx does not read .htaccess files; add a deny rule for your actual uploads URL path. With the default WordPress uploads path:</p><pre><code>location ^~ /wp-content/uploads/azuracast-logs/ { deny all; }</code></pre><pre style="max-height:500px;overflow:auto;background:white;padding:12px">' . esc_html(AZNS_Logger::tail()) . '</pre><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="azns_clear">';
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
