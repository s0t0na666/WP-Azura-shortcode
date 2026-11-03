# WP Azura Shortcode

WordPress plugin for embedding AzuraCast public pages and displaying radio components.

## Development status

Current source version: **1.3.4**.

The plugin uses AzuraCast public-page embeds for the full Player, History, Podcasts, and Schedule interfaces. Standalone Now Playing/Playing Next components consume AzuraCast's own Now Playing data instead of creating or estimating metadata in WordPress.

**Playing Next data flow:** the initial state is read from `/api/nowplaying/{station_shortcode}` through the plugin's public WordPress REST proxy. Real-time updates arrive over AzuraCast's `/api/live/nowplaying/websocket` connection, in the `pub.data.np.playing_next` field. If the WebSocket drops, the plugin refreshes the JSON snapshot while reconnecting. A `playing_next: null` response means AzuraCast currently has no next item to show; it is not treated as an API parsing error. A late REST response cannot overwrite a newer WebSocket publication.

AzuraCast 0.24.0 does not provide a dedicated public Player/Next embed, although its Now Playing data contains the `playing_next` field. The standalone shortcode therefore uses that authoritative data feed; it does not guess the next song from history.

The public AzuraCast demo's static Now Playing JSON was checked and contains `playing_next.song.artist`, `playing_next.song.title`, and `playing_next.song.art`. This confirms the documented data shape, but does **not** replace live testing against the target station.

Automated checks are configured for pushes and pull requests targeting the `development` branch:
- PHP syntax lint on PHP 8.1 and PHP 8.5.
- JavaScript syntax checking and Node.js regression tests for initial JSON, real-time WebSocket updates, an empty queue, and stale-response ordering.

The source has been reviewed against the AzuraCast 0.24.0 OpenAPI schema and public route definitions. Source review and CI syntax/unit checks do **not** replace runtime integration tests against the target AzuraCast instance and a staging WordPress site. See [TODO.md](TODO.md) for the remaining checks.

## Configuration

All stations are managed in the WordPress `azns_settings` option through the plugin's administration screen. Station shortcodes and server URLs are not hardcoded into the plugin source.

See [`readme.txt`](readme.txt) for installation and shortcode details, and [`CHANGELOG.md`](CHANGELOG.md) for the audit changes.
