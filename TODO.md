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
- [ ] Use AzuraCast public pages/official embeds for complete radio interfaces wherever AzuraCast provides them. WordPress must not recreate the full radio player or duplicate controls.
- [x] Keep WordPress responsible for station configuration, shortcode handling, and lightweight responsive embed/component wrappers.
- [x] For standalone data items that have no AzuraCast public embed, consume AzuraCast's own public API/WebSocket data; do not synthesize metadata or infer tracks from playback history.
- [x] Preserve existing WordPress shortcode names and dynamic station selection where practical, so existing pages can continue to use their current shortcode interface.
- [ ] Verify responsive sizing, theme selection, and embed behavior for each AzuraCast component on desktop and mobile.

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
- [ ] Live-test the actual 0.24.0 response and WebSocket publications on the target AzuraCast instance, including a queued track, an empty queue/live DJ, and reconnect behavior.

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
- [ ] Confirm the workflow completes successfully on GitHub for the development commit.

## Still requires WordPress/AzuraCast runtime testing
- [ ] Run PHP syntax checks on PHP 8.1 and PHP 8.5 (CI workflow).
- [ ] Run JavaScript syntax checks in supported browsers (CI covers Node syntax, not full browser compatibility).
- [ ] Test shortcodes and settings saves in a clean WordPress installation.
- [ ] Verify public-page iframe URLs, theme selection, responsiveness and actual sizing on desktop/mobile.
- [ ] Test against the target AzuraCast 0.24.0 instance, including a reverse-proxy subdirectory.
- [ ] Verify log-directory access denial on Apache/LiteSpeed and IIS; install the Nginx rule where applicable.
- [ ] Build and install a fresh ZIP on a staging WordPress site.

The public demo JSON confirms the data shape only. Source review and CI checks do not count as live integration tests.
