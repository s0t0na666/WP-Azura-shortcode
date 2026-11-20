# WP Azura Shortcode

WordPress plugin for embedding AzuraCast public pages and displaying radio components.

## Development status

Latest published release: [1.5.0](https://github.com/s0t0na666/WP-Azura-shortcode/releases/tag/v1.5.0).

The plugin uses AzuraCast public-page embeds for the full Player, History shortcode, Podcasts, and Schedule interfaces. The Gutenberg History block is a native WordPress list sourced from AzuraCast's `song_history` data and applies its own display limit. A compact Upcoming Show component reads AzuraCast's public Schedule endpoint and displays the next returned schedule entry. Standalone Now Playing/Playing Next components consume AzuraCast's own Now Playing data instead of creating or estimating metadata in WordPress.

**Playing Next data flow:** the initial state is read from `/api/nowplaying/{station_shortcode}` through the plugin's public WordPress REST proxy. Real-time updates arrive over AzuraCast's `/api/live/nowplaying/websocket` connection, in the `pub.data.np.playing_next` field. If the WebSocket drops, the plugin refreshes the JSON snapshot while reconnecting. A `playing_next: null` response means AzuraCast currently has no next item to show; it is not treated as an API parsing error. A late REST response cannot overwrite a newer WebSocket publication.

AzuraCast 0.24.0 does not provide a dedicated public Player/Next embed, although its Now Playing data contains the `playing_next` field. The standalone shortcode therefore uses that authoritative data feed; it does not guess the next song from history.

The public AzuraCast demo's static Now Playing JSON was checked and contains `playing_next.song.artist`, `playing_next.song.title`, and `playing_next.song.art`. This confirms the documented data shape, but does **not** replace live testing against the target station.

Automated checks are configured for pushes to `development` and pull requests targeting `development` or `main`:
- PHP syntax lint and CLI regression scripts on PHP 8.1 and PHP 8.5. The PHP tests cover station configuration and URL validation, the public API resource allow-list, proxy endpoint construction, cache TTLs, upstream failures, diagnostics privacy.
- JavaScript syntax checking and Node.js regression tests for all nine Gutenberg block registrations, initial Now Playing JSON, real-time WebSocket updates, an empty Playing Next queue, stale-response ordering, listener counts, Live DJ/AutoDJ states and shared per-station feed lifecycle.
- The official WordPress Plugin Check action runs against a clean installable archive, covering general, security, performance and accessibility checks.

The source has been reviewed against the AzuraCast 0.24.0 OpenAPI schema and public route definitions. CI includes PHP CLI regression tests for station settings and URL validation, API proxy behavior, Gutenberg registration/server-render callbacks and attributes, shortcode output/accessibility attributes, diagnostics privacy. The official WordPress Plugin Check reports no errors for the clean plugin archive. Source review and CI tests do **not** replace runtime integration tests against the target AzuraCast instance and a staging WordPress site. See [TODO.md](TODO.md) for the remaining checks.

## Gutenberg blocks

Version 1.4.0 added nine dynamic Gutenberg blocks:

- AzuraCast Player
- AzuraCast Now Playing
- AzuraCast Playing Next
- AzuraCast History (native recent-track list with a configurable display limit)
- AzuraCast Podcasts
- AzuraCast Schedule
- AzuraCast Upcoming Show (from the next public schedule entry)
- AzuraCast Listener Count
- AzuraCast Broadcast Mode (Live DJ / AutoDJ)

Each block can use the default station or select a configured station in the block sidebar. Blocks reuse the existing shortcode renderer on the server. The Gutenberg History block selects a native list renderer so its maximum-entry control works; the existing `[azura_history]` shortcode remains the official AzuraCast History iframe. The native list can show fewer entries than its selected maximum when the station's public API history setting returns fewer rows. The Upcoming Show component uses the first non-current entry returned by AzuraCast's already ordered schedule response; when there is no future entry, it shows the current entry if one exists. It does not calculate or invent schedule entries.

## WordPress administration and diagnostics

Use **AzuraCast → Stations** to manage stations and their appearance. Open **Shortcodes** to select a saved station and copy examples for the player, live data, history, podcasts, or schedule. The default station option produces unqualified shortcodes that follow the current playback default.

The **Logs & Diagnostics** screen can download a JSON report for support. It includes plugin, WordPress and PHP versions, configured station shortcodes, display theme/artwork settings, and sanitized recent log entries. It deliberately omits station server URLs and custom CSS. Complete URLs are removed from exported log messages, and credential/token fields and values are redacted. Download requires a WordPress administrator and a valid nonce.

Diagnostics retain the active log and one archive, up to 1 MiB each. By default they are stored under the WordPress uploads directory in `azuracast-logs`. To log outside the web root, define `AZNS_LOG_DIR` in `wp-config.php` as an absolute directory writable by PHP. Changing the directory directs subsequent logging there. Existing files stay in their original directory.

The administration form also reports skipped invalid shortcode rows, duplicate shortcodes, invalid server URLs and correction of an invalid default-station selection.

## Additional station indicators

Two lightweight components use the fields already included in AzuraCast's Now Playing JSON/WebSocket payload:

- `[azura_listeners station="your_station_shortcode"]` displays the current listener count from `listeners.current` (with `listeners.total` as a fallback).
- `[azura_broadcast_mode station="your_station_shortcode"]` displays **Live DJ** with the streamer name when `live.is_live` is true, or **AutoDJ** when it is false. If AzuraCast does not return a boolean live flag, the component reports that broadcast mode is unavailable instead of guessing.

Matching Gutenberg blocks are available as **AzuraCast Listener Count** and **AzuraCast Broadcast Mode**. Now Playing, Playing Next, History, Listener Count and Broadcast Mode components for the same station share one initial REST snapshot request and one browser-side WebSocket connection. Reconnects refresh one snapshot for that station; other configured stations keep independent connections.

## Artwork fallback

When artwork display is enabled, Now Playing and Playing Next show a consistent placeholder if AzuraCast does not provide a valid HTTP(S) artwork URL or the image cannot be loaded. The placeholder is removed once the image loads successfully and includes an accessible label for assistive technology. Now Playing, Playing Next and the listener/broadcast indicators also use polite, atomic live regions so screen readers can announce changed values.

## Configuration

All stations are managed in the WordPress `azns_settings` option through the plugin's administration screen. Station shortcodes and server URLs are not hardcoded into the plugin source.

See [`readme.txt`](readme.txt) for installation and shortcode details, and [`CHANGELOG.md`](CHANGELOG.md) for the audit changes.
