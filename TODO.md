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

## Next release proposal — 1.4.0

The next release should add useful WordPress-native workflows without replacing AzuraCast's own player, queue, history or schedule logic.

### Priority 0 — development and release workflow

- [ ] Keep all new feature work on `development`; do not develop directly on `main`.
- [x] Run the full CI workflow on pull requests targeting both `development` and `main`; editor bundle syntax and all Node regression tests are included.
- [ ] Add automated PHP unit tests for station configuration, URL validation, REST proxy allow/deny behavior and malformed API responses.
- [ ] Add mocked AzuraCast fixtures for live, offline, empty-queue, missing-artwork and WebSocket reconnect scenarios.
- [ ] Add a WordPress Plugin Check / coding-standards job and resolve actionable findings.
- [ ] Verify the supported WordPress core range, then update the `Tested up to` header only for versions actually tested.
- [x] Keep the release ZIP reproducible and verify that it excludes tests, CI files, logs, development notes and other non-installable files; include and validate the new block registration/editor files.

### Priority 1 — WordPress admin experience

- [x] Add an administrator-only per-station **Test connection** action for saved stations; it checks the public Now Playing endpoint, validates the returned AzuraCast payload and reports the HTTP result/station name.
- [ ] Show a compact station health summary in settings: reachable/unreachable, station identity and last successful check.
- [ ] Improve validation feedback for duplicate station shortcodes, invalid URLs, missing station data and an empty default-station selection.
- [ ] Provide a safe diagnostics export that redacts credentials and private values; never expose secrets in browser JavaScript or public REST responses.
- [ ] Test the connection-check AJAX action on staging for success, timeout, HTTP error, malformed JSON, invalid nonce and insufficient permissions.

### Priority 1 — native Gutenberg blocks

- [x] Register dynamic Gutenberg blocks for Player, Now Playing, Playing Next, History, Podcasts and Schedule on `development`.
- [x] Provide a default/configured station selector and a History entry-limit control in the block sidebar.
- [x] Reuse the existing shortcode rendering implementation for dynamic block output.
- [x] Keep existing shortcodes fully backward-compatible; block support is additive.
- [ ] Verify block insertion, sidebar controls, saved attributes and frontend rendering on the staging WordPress site (runtime editor testing is still required).
- [ ] Verify the station connection test in the WordPress admin against the configured AzuraCast station.

### Priority 2 — additional station data

- [ ] Evaluate public AzuraCast data for current listener count and live/offline status.
- [ ] Add optional listener-count and station-status components only after confirming the relevant public API fields and behavior against the target AzuraCast version.
- [ ] Review current artwork handling and provide consistent fallbacks for missing, invalid or unavailable artwork.
- [ ] Consider a compact upcoming-show component using the public schedule data; do not calculate or invent schedule entries in WordPress.

### Priority 2 — performance, accessibility and presentation

- [ ] Measure REST request frequency and WebSocket reconnect behavior before introducing caching or polling changes.
- [ ] If caching is needed, use a short TTL for REST snapshots only; never cache or delay live WebSocket publications.
- [ ] Review loading, empty, offline and error states for consistent styling and clear user feedback.
- [ ] Add/verify accessible labels, meaningful artwork alt text, keyboard behavior and screen-reader announcements for changing Now Playing information.
- [ ] Keep the plugin lightweight and avoid loading frontend assets on pages that do not use its shortcodes or blocks.

### Later milestone — builder integrations (candidate 1.5.0)

- [ ] Add native Elementor widgets for the supported station components.
- [ ] Add Divi 5 modules; investigate the requested persistent bottom mini-player/dock on scroll as a separate, opt-in feature.
- [ ] Evaluate Beaver Builder integration after the Gutenberg and Elementor implementations are stable.
- [ ] Document compatibility with the Classic Editor and popular themes; retain shortcodes as the universal fallback.

## 1.4.0 release acceptance checklist

- [ ] All feature work is merged into `development`; no unfinished feature is included in the release candidate.
- [ ] PHP, JavaScript, unit, integration and WordPress Plugin Check workflows pass.
- [ ] Test on a staging WordPress installation with the supported PHP versions and the target AzuraCast version.
- [ ] Re-test every shortcode and block, multiple stations, settings save/update, desktop/tablet/mobile and Light/Dark themes.
- [ ] Re-test online/offline state, empty queue, artwork fallback, WebSocket reconnect and browser back/forward navigation.
- [ ] Test fresh installation and upgrade from 1.3.4, including rollback/recovery notes.
- [ ] Update plugin version, `readme.txt`, `CHANGELOG.md`, documentation and ZIP filename consistently.
- [ ] Build and inspect the clean installable ZIP from the release tag.
- [ ] Open a pull request from `development` to `main`; merge only after checks pass and the release candidate is accepted.
- [ ] Tag and publish the new release from the merged `main` commit.

## Architecture and operational notes

- AzuraCast remains the source of truth for playback, queue, history, podcasts and schedule data.
- WordPress handles configuration, layout integration and lightweight presentation; it should not recreate AzuraCast's streaming/player internals.
- The plugin uses public endpoints and must not require an API key in browser-side code.
- Keep station URLs and shortcodes configurable per station; do not hardcode Mugadavanje-specific values.
- The plugin diagnostic log directory is `wp-content/uploads/azuracast-logs/`. This is not the AzuraCast server log, cPanel error log, Apache access log or WordPress `debug.log`.
- Apache/LiteSpeed protection uses `.htaccess`; IIS uses `web.config`; Nginx needs a server-level deny rule because it does not read `.htaccess`.
