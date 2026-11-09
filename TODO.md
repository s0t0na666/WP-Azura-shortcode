# WIP review checklist

## Source review completed for 1.3.3
- [x] Review station configuration lookup and remove built-in station fallback.
- [x] Validate administrator station rows, URLs, duplicate shortcodes, and default selection.
- [x] Verify API paths and key response fields against the AzuraCast 0.24.0 OpenAPI schema.
- [x] Verify public podcast listing and episode routes against AzuraCast 0.24.0 route definitions.
- [x] Review public REST proxy behavior and avoid following upstream redirects.
- [x] Review WebSocket initial publication and reconnect handling in the frontend source.
- [x] Fix successful Now Playing loads leaving the initial status text visible.
- [x] Review browser pagehide/pageshow handling and reconnect widgets after bfcache restore.
- [x] Validate album-art URLs before assigning them to image elements.
- [x] Review log credential redaction and add Apache 2.4+, legacy Apache and IIS protection files.
- [x] Document the Nginx log-directory deny rule.

## Architecture requirement: AzuraCast as the source of truth
- [x] Use AzuraCast public pages/official embeds for complete radio interfaces wherever AzuraCast provides them. WordPress must not recreate the full radio player or duplicate controls.
- [x] Keep WordPress responsible for station configuration, shortcode handling, and lightweight responsive embed/component wrappers.
- [x] For standalone data items that have no AzuraCast public embed, consume AzuraCast's own public API/WebSocket data; do not synthesize metadata or infer tracks from playback history.
- [x] Preserve existing WordPress shortcode names and dynamic station selection where practical, so existing pages can continue to use their current shortcode interface.
- [x] Verify responsive sizing, theme selection, and embed behavior for each AzuraCast component on desktop, tablet and mobile (user-confirmed 2026-11-09).

## AzuraCast 0.24.0 public UI and data inventory (source reviewed)
- [x] Confirm the official Player embed is available at the station public page's `/embed` route and is rendered by AzuraCast's `Public/Player` Vue component.
- [x] Confirm the AzuraCast Player component renders the current track, album artwork, live-DJ state, audio controls, stream selection, and supported player controls itself.
- [x] Confirm AzuraCast provides public History, Podcasts, and Schedule pages; Podcasts and Schedule support embedded mode, while History is already rendered using a minimal public page.
- [x] Inspect AzuraCast 0.24.0 `Public/Player.vue`: it does not render the queued `playing_next` item.
- [x] Inspect AzuraCast 0.24.0 public routes: no separate public `next-track` page/embed route was found.
- [x] Confirm the documented public Now Playing JSON endpoint format at `/api/nowplaying/{station_shortcode}` and static JSON endpoint format at `/api/nowplaying_static/{station_shortcode}.json`.
- [x] Inspect the public AzuraCast demo JSON: `playing_next` is an object with a `song` containing artist, title and artwork fields.
- [x] Confirm the documented WebSocket publication exposes the Now Playing object at `pub.data.np`; the next item is available at `pub.data.np.playing_next`.
- [x] Keep the existing `[azura_next_track]` shortcode data-driven: initial snapshot from the JSON API through the WordPress REST proxy, then real-time updates from the AzuraCast WebSocket.
- [x] Retain JSON refresh when reconnecting the WebSocket, so the widget recovers its current state after a dropped connection.
- [x] Show a distinct empty state when AzuraCast explicitly returns `playing_next: null`, rather than treating it as a malformed/missing response.
- [x] Prevent a late REST response from overwriting newer WebSocket data or replacing it with an old error state.
- [x] User-confirmed functional test against the configured AzuraCast/WordPress setup: player, Now Playing, Playing Next, History, Podcasts and Schedule all work (reported 2026-11-09).
- [x] Verify actual Now Playing JSON/WebSocket data updates Playing Next with a queued track (covered by the user's successful end-to-end test).
- [ ] Separately verify edge cases: empty queue/live-DJ state, WebSocket interruption/reconnect and reverse-proxy subdirectory behavior (user has not yet tested these).

Source references:
- [AzuraCast 0.24.0 PlayerAction](https://github.com/AzuraCast/AzuraCast/blob/0.24.0/backend/src/Controller/Frontend/PublicPages/PlayerAction.php)
- [AzuraCast 0.24.0 Player.vue](https://github.com/AzuraCast/AzuraCast/blob/0.24.0/frontend/components/Public/Player.vue)
- [AzuraCast 0.24.0 public routes](https://github.com/AzuraCast/AzuraCast/blob/0.24.0/backend/config/routes/public.php)
- [AzuraCast 0.24.0 public API routes](https://github.com/AzuraCast/AzuraCast/blob/0.24.0/backend/config/routes/api_station.php)
- [AzuraCast Now Playing Data API and WebSocket format](https://www.azuracast.com/docs/developers/now-playing-data/)
- [Public AzuraCast demo JSON](https://demo.azuracast.com/api/nowplaying_static/azuratest_radio.json)

## Automated checks
- [x] Add frontend regression tests for JSON initial state, WebSocket updates, empty queues, and stale REST-response ordering.
- [x] Add GitHub Actions workflow for PHP syntax checks on PHP 8.1/8.5 and JavaScript syntax/behavior checks.
- [x] Confirm the workflow completes successfully on GitHub for the development commit (run 41670958961: PHP 8.1, PHP 8.5, Node.js syntax and all frontend regression tests passed).

## User-reported integration test — 2026-11-09
The user confirms that the normal plugin functionality, WordPress installation/settings saving, responsive layout (desktop/tablet/mobile), and Light/Dark themes all work. These are recorded as user-confirmed, not as independently executed tests by the maintainer. Empty-queue/reconnect behavior and server log-directory access rules have not yet been tested.

## Remaining WordPress/AzuraCast runtime and release checks
- [x] Run PHP syntax checks on PHP 8.1 and PHP 8.5 (GitHub Actions passed).
- [x] Run JavaScript syntax check and frontend regression tests in Node.js (GitHub Actions passed).
- [x] Verify JavaScript behavior and responsive layout on desktop, tablet and mobile (user-confirmed 2026-11-09).
- [x] Verify all functional shortcodes render and work on the user's test WordPress site (user-confirmed 2026-11-09).
- [x] Install plugin on WordPress and verify normal station setup/settings saving (user-confirmed 2026-11-09).
- [ ] Test invalid station rows, duplicate shortcodes and empty configuration handling separately.
- [x] Verify the Player, History, Podcasts and Schedule public-page embeds load on the user's test site (user-confirmed as part of the complete element test, 2026-11-09).
- [x] Verify Light/Dark theme selection and responsive iframe sizing on desktop, tablet and mobile (user-confirmed 2026-11-09).
- [x] Verify the plugin's radio elements against the configured target AzuraCast instance (user-confirmed 2026-11-09).
- [ ] Separately test an AzuraCast installation served from a reverse-proxy subdirectory.
- [ ] Verify that the plugin's own log directory is not publicly accessible on the shared host (see the log-protection note below).
- [x] Install and test the current development build on the user's WordPress test site (user-confirmed 2026-11-09).
- [ ] Prepare a clean, versioned release ZIP and verify its contents before tagging a release.

### Log directory: what is being protected?
The plugin writes its own diagnostic log files to `wp-content/uploads/azuracast-logs/`. These are not the AzuraCast broadcast logs, cPanel error logs, Apache access logs or WordPress `debug.log`. The directory may contain technical error details and is intended for administrators only. The plugin creates `.htaccess` protection for Apache/LiteSpeed and `web.config` protection for IIS. Nginx does not read `.htaccess`; it needs a server-level `location` deny rule. On shared hosting, first check the direct URL `https://YOUR-DOMAIN/wp-content/uploads/azuracast-logs/` and, if you know a log filename, try opening that exact file URL. A 403/404 or an access-denied response is expected; if a log file displays in the browser, protection is missing. Do not delete the directory or change server configuration until the host/web-server type is confirmed.

The public demo JSON confirms the data shape only. Source review and CI checks do not count as live integration tests.
