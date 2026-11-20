# WP Azura Shortcode — project checklist

## Stable release 1.3.4 — completed

- [x] Complete source audit and harden station configuration, REST proxy handling and diagnostic logging.
- [x] Keep configured stations dynamic through the `azns_settings` option; no hardcoded station URL or shortcode.
- [x] Use AzuraCast's official public pages for Player, History, Podcasts and Schedule.
- [x] Use AzuraCast JSON and WebSocket data for Now Playing and Playing Next.
- [x] Handle empty Playing Next queue, WebSocket reconnects and stale REST responses.
- [x] Preserve existing shortcode names and multi-station selection.
- [x] Confirm the public API routes and response fields against the AzuraCast 0.24.0 source/schema.
- [x] Add PHP syntax checks for PHP 8.1 and 8.5, plus JavaScript syntax and regression tests.
- [x] Build a clean WordPress-installable ZIP and verify its contents.
- [x] Publish Git tag `v1.3.4` and GitHub Release with `azuracast-native-suite-1.3.4.zip` attached.
- [x] Merge the release into `main` through [PR #2](https://github.com/s0t0na666/WP-Azura-shortcode/pull/2), preserving both branch histories.
- [x] User-confirmed end-to-end testing: all plugin elements work; installation and settings saving work; responsive desktop/tablet/mobile layouts and Light/Dark themes work; the configured AzuraCast connection and live data work.

### 1.3.4 validation notes

The user confirmed that all functionality has been tested successfully. GitHub Actions separately passed PHP lint on PHP 8.1 and 8.5, JavaScript syntax checks, frontend regression tests and clean ZIP packaging. These are distinct forms of validation; the runtime integration testing was performed by the user.

## Released 1.4.0

[Release and installable ZIP](https://github.com/s0t0na666/WP-Azura-shortcode/releases/tag/v1.4.0). See [CHANGELOG.md](CHANGELOG.md) for the published changes.

## Proposed follow-up

### Priority 0 — maintenance release

- [x] Remove the manual connection test, its AJAX handler and the old status indicators from admin and diagnostics.
- [ ] On staging, upgrade from 1.4.0 and verify station settings, the default station, embedded playback and live Now Playing/Playing Next data. Check station add/remove and sanitized diagnostics export.
- [ ] Exercise all nine blocks in the editor and frontend, including History limits and Upcoming Show with an empty schedule. Record verified WordPress and PHP versions.
- [ ] Verify the ZIP produced by CI and make the Plugin Check job discover the generated archive instead of hardcoding the 1.4.0 filename.

### Priority 1 — live data and accessibility

- [ ] Verify how live widgets recover after temporary network loss and browser back/forward navigation with two real stations. Confirm each station shares one WebSocket and refreshes its own REST snapshot on reconnect.
- [ ] Check keyboard navigation and screen-reader announcements in the station settings, editor and frontend. Include track changes and empty or unavailable data.
- [ ] Check actual frontend asset loading on pages with no components, iframe-only embeds and native blocks. Address any unnecessary requests found during testing.

### Priority 2 — builder integrations

- [ ] Evaluate native Elementor widgets that reuse the shortcode and Gutenberg renderers.
- [ ] Assess Divi 5 modules, an opt-in persistent mini-player and Beaver Builder separately after the core integration checks.

## Architecture and operational notes

- AzuraCast remains the source of truth for playback, queue, history, podcasts and schedule data.
- WordPress handles configuration, layout integration and lightweight presentation; it should not recreate AzuraCast's streaming/player internals.
- The plugin uses public endpoints and must not require an API key in browser-side code.
- Keep station URLs and shortcodes configurable per station; do not hardcode Mugadavanje-specific values.
- The plugin diagnostic log directory is `wp-content/uploads/azuracast-logs/`. This is not the AzuraCast server log, cPanel error log, Apache access log or WordPress `debug.log`.
- Apache/LiteSpeed protection uses `.htaccess`; IIS uses `web.config`; Nginx needs a server-level deny rule because it does not read `.htaccess`.
