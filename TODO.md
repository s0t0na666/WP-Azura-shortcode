# Release checklist — WP Azura Shortcode 1.3.4

## Source review and architecture
- [x] Remove built-in station fallback; read configured stations from the `azns_settings` option.
- [x] Validate station URLs, malformed settings, duplicate station shortcodes and default selection.
- [x] Harden administrator settings saves against malformed arrays and invalid station rows.
- [x] Verify public API paths and response fields against the AzuraCast 0.24.0 OpenAPI schema.
- [x] Verify public podcast listing/episode routes and public schedule route.
- [x] Harden REST proxy behavior and do not follow upstream redirects.
- [x] Review log credential redaction and add Apache 2.4+, legacy Apache and IIS protection files.
- [x] Document the Nginx log-directory deny rule.
- [x] Review WebSocket subscription, initial publication, reconnect handling, pagehide/pageshow and stale REST response ordering.

## AzuraCast-rendered UI and data flow
- [x] Use AzuraCast public pages/official embeds for the complete Player, History, Podcasts and Schedule interfaces.
- [x] Keep WordPress responsible for station configuration, shortcode handling and lightweight wrappers.
- [x] Consume AzuraCast public JSON/WebSocket data for standalone Now Playing and Playing Next information.
- [x] Preserve existing shortcode names and dynamic station selection.
- [x] Confirm that AzuraCast 0.24.0 Player.vue does not include a separate Playing Next display.
- [x] Confirm `playing_next` is available from `/api/nowplaying/{station_shortcode}` and in WebSocket `pub.data.np.playing_next`.
- [x] Add explicit empty-queue handling and prevent an older REST snapshot from overwriting newer WebSocket data.

## User-confirmed integration checks — 2026-11-09
The user reports that all elements work correctly, responsive layouts and both themes work, and the plugin is installed and saving settings on WordPress. The user then explicitly asked to proceed on the assumption that all remaining tests passed. Accordingly, the following are recorded as user-assumed successful rather than independently observed by the maintainer. The plugin log file had been manually deleted before this confirmation; its absence alone is not proof of an HTTP access-denied response.

- [x] Test all plugin elements: Player, Now Playing, Playing Next, History, Podcasts and Schedule.
- [x] Test initial WordPress installation, station setup and settings saving.
- [x] Test first-install/invalid-row/duplicate/empty-configuration behavior (assumed successful per user instruction).
- [x] Test desktop, tablet and mobile layouts.
- [x] Test AzuraCast Light and Dark themes.
- [x] Test public-page embeds and responsive sizing.
- [x] Test the configured AzuraCast instance, JSON/Playing Next data and WebSocket updates.
- [x] Test empty queue, live-DJ state, WebSocket interruption/reconnect and reverse-proxy subdirectory behavior (assumed successful per user instruction).
- [x] Test plugin log-directory access protection on shared hosting (assumed successful per user instruction; the user had deleted `azuracast.log` from `public_html/dev.mugadavanje.rs/wp-content/uploads/azuracast-logs` before confirming).

## Automated checks
- [x] PHP syntax checks on PHP 8.1 and 8.5 (GitHub Actions passed).
- [x] JavaScript syntax check and frontend regression tests (GitHub Actions passed: JSON initial state, WebSocket publication, empty queue and stale-response race).

## Release 1.3.4
- [x] Add a deterministic script to assemble a clean WordPress-installable ZIP containing only the plugin entry file, `includes/`, `assets/` and `readme.txt`.
- [x] Add a GitHub Actions packaging job that validates and uploads the ZIP as a workflow artifact.
- [ ] Confirm the packaging job succeeds and inspect the generated ZIP artifact.
- [ ] Publish Git tag `v1.3.4` and GitHub Release with the ZIP attached.
- [ ] Keep `main` unchanged until the release candidate is accepted; merge only as a separate, explicit step.

## Log directory reference
The plugin's own diagnostic log directory is `wp-content/uploads/azuracast-logs/`; this is not the AzuraCast broadcast log, cPanel error log, Apache access log or WordPress `debug.log`. The plugin writes `azuracast.log` there only when it has a diagnostic event. Apache/LiteSpeed protection is provided by `.htaccess`; IIS uses `web.config`. Nginx does not read `.htaccess` and needs a server-level deny rule. Since the user deleted the log file, there may be no `azuracast.log` file at present.
