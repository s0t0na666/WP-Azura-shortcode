# WP Azura Shortcode

WordPress plugin for embedding AzuraCast public pages and displaying radio components.

## Development status

Current development source version: **1.4.0**.

The plugin uses AzuraCast public-page embeds for the full Player, History, Podcasts, and Schedule interfaces. Standalone Now Playing/Playing Next components consume AzuraCast's own Now Playing data instead of creating or estimating metadata in WordPress.

**Playing Next data flow:** the initial state is read from `/api/nowplaying/{station_shortcode}` through the plugin's public WordPress REST proxy. Real-time updates arrive over AzuraCast's `/api/live/nowplaying/websocket` connection, in the `pub.data.np.playing_next` field. If the WebSocket drops, the plugin refreshes the JSON snapshot while reconnecting. A `playing_next: null` response means AzuraCast currently has no next item to show; it is not treated as an API parsing error. A late REST response cannot overwrite a newer WebSocket publication.

AzuraCast 0.24.0 does not provide a dedicated public Player/Next embed, although its Now Playing data contains the `playing_next` field. The standalone shortcode therefore uses that authoritative data feed; it does not guess the next song from history.

The public AzuraCast demo's static Now Playing JSON was checked and contains `playing_next.song.artist`, `playing_next.song.title`, and `playing_next.song.art`. This confirms the documented data shape, but does **not** replace live testing against the target station.

Automated checks are configured for pushes and pull requests targeting the `development` branch:
- PHP syntax lint and CLI regression scripts on PHP 8.1 and PHP 8.5. The PHP tests cover station configuration and URL validation, the public API resource allow-list, proxy endpoint construction, cache TTLs, upstream failures, the administrator connection check and diagnostics privacy.
- JavaScript syntax checking and Node.js regression tests for all eight Gutenberg block registrations, initial Now Playing JSON, real-time WebSocket updates, an empty Playing Next queue, stale-response ordering, listener counts and Live DJ/AutoDJ states.
- The official WordPress Plugin Check action runs against a clean installable archive, covering general, security, performance and accessibility checks.

The source has been reviewed against the AzuraCast 0.24.0 OpenAPI schema and public route definitions. Source review and CI syntax/unit checks do **not** replace runtime integration tests against the target AzuraCast instance and a staging WordPress site. See [TODO.md](TODO.md) for the remaining checks.

## Gutenberg blocks — 1.4.0 development

The `development` branch now registers eight dynamic Gutenberg blocks:

- AzuraCast Player
- AzuraCast Now Playing
- AzuraCast Playing Next
- AzuraCast History (with a configurable entry limit)
- AzuraCast Podcasts
- AzuraCast Schedule
- AzuraCast Listener Count
- AzuraCast Broadcast Mode (Live DJ / AutoDJ)

Each block can use the default station or select a configured station in the block sidebar. Blocks reuse the existing shortcode renderer on the server, so the frontend output and AzuraCast data flow remain shared with the shortcode implementation. This work is under development and is not part of the published 1.3.4 release.

## WordPress administration — connection test

The station settings screen includes an administrator-only **Test connection** action for each saved station. It requests the configured station's public Now Playing endpoint from the WordPress server, validates the returned AzuraCast station payload, and reports the HTTP result and station name. The request is protected by a WordPress nonce and the `manage_options` capability; it does not expose the server URL or any credentials to the public frontend. New or changed station settings must be saved before they can be tested.

## Station health and diagnostics — 1.4.0 development

Each saved station has a manual **Test connection** action. The latest result is retained in the `azns_station_health` option and shown beside the station settings, including the last check time and the last successful check. This is an on-demand check, not continuous background polling. Health history for stations removed from settings is discarded when the configuration is saved.

The **Logs & Diagnostics** screen can download a JSON report for support. It includes plugin, WordPress and PHP versions, configured station shortcodes, display theme/artwork settings, connection-check timestamps/statuses, and sanitized recent log entries. It deliberately omits station server URLs and custom CSS. Complete URLs are removed from exported log messages, and credential/token fields and values are redacted. Download requires a WordPress administrator and a valid nonce.

The administration form also reports skipped invalid shortcode rows, duplicate shortcodes, invalid server URLs and correction of an invalid default-station selection.

## Additional station indicators — 1.4.0 development

Two lightweight components use the fields already included in AzuraCast's Now Playing JSON/WebSocket payload:

- `[azura_listeners station="your_station_shortcode"]` displays the current listener count from `listeners.current` (with `listeners.total` as a fallback).
- `[azura_broadcast_mode station="your_station_shortcode"]` displays **Live DJ** with the streamer name when `live.is_live` is true, or **AutoDJ** when it is false. If AzuraCast does not return a boolean live flag, the component reports that broadcast mode is unavailable instead of guessing.

Matching Gutenberg blocks are available as **AzuraCast Listener Count** and **AzuraCast Broadcast Mode**. Both use the existing Now Playing REST/WebSocket connection and do not add extra requests.

## Artwork fallback — 1.4.0 development

When artwork display is enabled, Now Playing and Playing Next show a consistent placeholder if AzuraCast does not provide a valid HTTP(S) artwork URL or the image cannot be loaded. The placeholder is removed once the image loads successfully and includes an accessible label for assistive technology.

## Configuration

All stations are managed in the WordPress `azns_settings` option through the plugin's administration screen. Station shortcodes and server URLs are not hardcoded into the plugin source.

See [`readme.txt`](readme.txt) for installation and shortcode details, and [`CHANGELOG.md`](CHANGELOG.md) for the audit changes.
