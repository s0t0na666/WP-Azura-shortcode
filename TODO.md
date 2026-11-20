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

Staging validation confirmed by the maintainer: the plugin connects to AzuraCast stations and renders components through shortcodes.

## 1.4.1 release candidate

The post-release source audit covered the `v1.4.0` tag and the admin changes in [PR #4](https://github.com/s0t0na666/WP-Azura-shortcode/pull/4).

### Priority 0 — maintenance release

- [x] Remove the manual connection test, its AJAX handler and the old status indicators from admin and diagnostics ([PR #4](https://github.com/s0t0na666/WP-Azura-shortcode/pull/4)).
- [ ] On staging, upgrade from 1.4.0 and verify station settings, the default station, embedded playback and live Now Playing/Playing Next data. Check station add/remove and sanitized diagnostics export.
- [ ] Exercise all nine blocks in the editor and frontend, including History limits and Upcoming Show with an empty schedule. Record verified WordPress and PHP versions.
- [x] Make the Plugin Check job discover the generated ZIP.
- [x] Build and inspect the installable ZIP locally with PHP 8.2.
- [x] Confirm CI generated the installable artifact and WordPress Plugin Check passed for the original PR #4 staging build.
- [ ] Confirm CI and the versioned ZIP for the 1.4.1 candidate.

Staging verification on WordPress 7.1.4 and PHP 8.1.34 confirmed the PR #4 candidate is active, the default station is still `mugadavanje`, and all nine Gutenberg blocks return HTML through the authenticated block renderer. History renders the requested limit of 3. Live WordPress-to-AzuraCast checks are deferred due to the hosting DNS issue. Interactive editor and frontend checks remain open.

### Priority 1 — reliability and accessibility

- [x] Give Playing Next a visible, accessible loading and error state. Its template omits the status element, so request errors and reconnect messages cannot be shown there. Keep the empty-queue state distinct.
- [x] Recover when a WebSocket opens but stops delivering usable station data. Add a timeout for the initial publication, detect stale data and use a bounded REST fallback with backoff. Preserve one shared feed per station and retire fallback requests when live updates resume.
- [x] Refresh Upcoming Show on long-open pages. It currently loads once at startup (or on back/forward cache restore), so a show can remain marked upcoming after it starts. Verify refreshes respect the Schedule cache and are shared by duplicate widgets.
- [x] Validate Now Playing payloads before publishing them and skip malformed History rows. A null history row currently interrupts rendering and can prevent other widgets sharing the feed from receiving the update.
- [x] Make the Default station selector follow row additions, shortcode edits and deletions before saving. The options currently reflect the rows saved at page load.
- [x] Rotate diagnostic logs at 1 MiB with one retained archive. Allow a private directory via `AZNS_LOG_DIR`.
- [ ] Verify that diagnostic log files are inaccessible via HTTP on supported hosting setups.

- [ ] Verify how live widgets recover after temporary network loss and browser back/forward navigation with two real stations. Confirm each station shares one WebSocket and refreshes its own REST snapshot on reconnect.
- [ ] Check keyboard navigation and screen-reader announcements in the station settings, editor and frontend. Include track changes and empty or unavailable data.
- [x] Inspect the HTML of published staging pages for conditional asset delivery. The mixed component page, `/elementor-2326/`, includes the frontend CSS and JS. Five pages without AzuraCast Suite components omit both references.
- [x] Maintainer confirmed staging components and assets load correctly.
- [ ] Capture browser network requests on isolated iframe-only and native Gutenberg test pages.

### Priority 2 — documentation and builder integrations

- [x] Add Upcoming Show to the admin shortcode reference and explain that the History limit applies to the native Gutenberg renderer; the default History shortcode embeds the AzuraCast history page.

- [x] Assess builder integration boundaries. Native Elementor, Divi and Beaver Builder adapters can reuse the station model and shortcode renderer. Editor preview and frontend asset loading must be tested in each builder with its own fixtures.
- [ ] Prototype a native Elementor widget with component and station controls.
- [ ] Prototype and test native Divi 5 and Beaver Builder modules with the same controls.
- [ ] Prototype a separate opt-in mini-player. A player that survives full page navigation requires its own playback lifecycle and browser testing.

## Architecture and operational notes

- AzuraCast remains the source of truth for playback, queue, history, podcasts and schedule data.
- WordPress handles configuration, layout integration and lightweight presentation; it should not recreate AzuraCast's streaming/player internals.
- The plugin uses public endpoints and must not require an API key in browser-side code.
- Keep station URLs and shortcodes configurable per station; do not hardcode Mugadavanje-specific values.
- The plugin diagnostic log directory is `wp-content/uploads/azuracast-logs/`. This is not the AzuraCast server log, cPanel error log, Apache access log or WordPress `debug.log`.
- Apache/LiteSpeed protection uses `.htaccess`; IIS uses `web.config`; Nginx needs a server-level deny rule because it does not read `.htaccess`.
