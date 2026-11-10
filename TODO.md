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

## Active development — 1.4.0

The next release should add useful WordPress-native workflows without replacing AzuraCast's own player, queue, history or schedule logic.

### Priority 0 — development and release workflow

- [x] Keep all new feature work on `development`; do not develop directly on `main`.
- [x] Run the full CI workflow on pull requests targeting both `development` and `main`; editor bundle syntax and all Node regression tests are included.
- [x] Add PHP CLI regression tests for station configuration, URL validation, REST resource allow-listing, endpoint construction, cache TTLs, malformed API responses and REST proxy status handling.
- [x] Add CLI regression tests for the administrator-only station connection action (permission, nonce, unknown station, success, upstream HTTP error, malformed payload and network failure).
- [x] Add mocked AzuraCast payload scenarios for live DJ/AutoDJ, API-unavailable state, empty queue, missing/invalid/failed artwork and WebSocket reconnect with snapshot refresh.
- [x] Add the official WordPress Plugin Check job; the clean plugin archive passed the general, security, performance and accessibility categories.
- [ ] Verify the supported WordPress core range, then update the `Tested up to` header only for versions actually tested.
- [x] Keep the release ZIP reproducible and verify that it excludes tests, CI files, logs, development notes and other non-installable files; include and validate the new block registration/editor files.

### Priority 1 — WordPress admin experience

- [x] Add an administrator-only per-station **Test connection** action for saved stations; it checks the public Now Playing endpoint, validates the returned AzuraCast payload and reports the HTTP result/station name.
- [x] Show a compact station health summary in settings: reachable/unreachable, station identity, last check time and last successful check; results are stored per configured station and are updated only by manual checks.
- [x] Improve validation feedback for duplicate station shortcodes, invalid URLs, empty/invalid shortcodes and an invalid default-station selection.
- [x] Provide an administrator-only JSON diagnostics export with environment details, station health and sanitized logs; station server URLs and custom CSS are omitted, complete URLs are removed from exported logs, and credential/token values are redacted.
- [x] Add CLI regression coverage for permission/nonce checks, success, unknown station, HTTP/network errors, invalid payload and preservation of the last successful health timestamp.
- [x] Add CLI regression coverage confirming diagnostic exports omit station URLs/custom CSS and redact URL credentials, tokens and sensitive log fields.
- [ ] Verify the health status display and diagnostics download against the staging WordPress installation.

### Priority 1 — native Gutenberg blocks

- [x] Register dynamic Gutenberg blocks for Player, Now Playing, Playing Next, History, Podcasts, Schedule, Listener Count and Broadcast Mode on `development`.
- [x] Provide a default/configured station selector and a History entry-limit control in the block sidebar; the native History block renderer now applies the selected limit while the existing History shortcode remains unchanged.
- [x] Reuse the existing shortcode rendering implementation for dynamic block output.
- [x] Keep existing shortcodes fully backward-compatible; block support is additive.
- [x] Add Node.js editor tests covering registration of all eight blocks and the History limit control.
- [x] Add PHP CLI tests for dynamic block registration, editor configuration, server-render callbacks, station sanitization and History limit bounds.
- [x] Keep the legacy History shortcode as the official AzuraCast iframe and render Gutenberg History natively from `song_history` so its display limit has an effect.
- [ ] Verify block insertion, sidebar controls, saved attributes and frontend rendering on the staging WordPress site (runtime editor testing is still required).
- [ ] Verify the station connection test in the WordPress admin against the configured AzuraCast station.

### Priority 2 — additional station data

- [x] Evaluate public AzuraCast data for current listener count and live broadcast mode; do not equate AutoDJ with a server outage.
- [x] Add optional listener-count and broadcast-mode components using the existing Now Playing payload: `listeners.current` (fallback `listeners.total`) and the explicit boolean `live.is_live`. The component displays Live DJ/AutoDJ and does not claim server online/offline status.
- [x] Add a consistent, accessible artwork placeholder for missing/invalid URLs and image-load failures; remove it only after a valid image loads.
- [x] Add frontend tests for listener counts, Live DJ and AutoDJ states.
- [ ] Consider a compact upcoming-show component using the public schedule data; do not calculate or invent schedule entries in WordPress.
- [x] Add Node.js regression tests for listener count and both Live DJ/AutoDJ states.

### Priority 2 — performance, accessibility and presentation

- [x] Measure REST request frequency and WebSocket reconnect behavior: multiple live components for one station now share one initial REST snapshot and one WebSocket; reconnect refreshes one snapshot, while separate stations remain isolated. No additional polling or live-update caching was introduced.
- [ ] If caching is needed, use a short TTL for REST snapshots only; never cache or delay live WebSocket publications.
- [x] Cover core loading, empty-queue, unavailable API, missing-artwork and reconnect/error states with clear user feedback and regression tests.
- [ ] Complete a full staging accessibility review for keyboard and screen-reader behavior; current code provides artwork alt text/fallback labels and polite atomic live regions for changing Now Playing, Playing Next and listener/broadcast indicators.
- [ ] Keep the plugin lightweight and avoid loading frontend assets on pages that do not use its shortcodes or blocks.

### Later milestone — builder integrations (candidate 1.5.0)

- [ ] Add native Elementor widgets for the supported station components.
- [ ] Add Divi 5 modules; investigate the requested persistent bottom mini-player/dock on scroll as a separate, opt-in feature.
- [ ] Evaluate Beaver Builder integration after the Gutenberg and Elementor implementations are stable.
- [ ] Document compatibility with the Classic Editor and popular themes; retain shortcodes as the universal fallback.

## 1.4.0 release acceptance checklist

- [x] All currently implemented feature work is committed to `development`; remaining items are staging validation and release-readiness tasks. No changes have been merged into `main`.
- [x] PHP CLI, JavaScript regression, package validation and WordPress Plugin Check workflows pass on the current development candidate (live WordPress/AzuraCast staging validation remains separate).
- [ ] Test on a staging WordPress installation with the supported PHP versions and the target AzuraCast version.
- [ ] Re-test every shortcode and block, multiple stations, settings save/update, desktop/tablet/mobile and Light/Dark themes.
- [ ] Re-test online/offline state, empty queue, artwork fallback, WebSocket reconnect and browser back/forward navigation, plus the new Listener Count and Broadcast Mode components on the staging site.
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
