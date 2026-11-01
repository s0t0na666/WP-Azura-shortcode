# WP Azura Shortcode

WordPress plugin for embedding AzuraCast public pages and displaying live Now Playing and Playing Next data.

## Development status

Current audited source version: **1.3.3**.

The source has been reviewed against the AzuraCast 0.24.0 OpenAPI schema and public route definitions. The review covered station configuration, administrator settings handling, REST proxy paths, podcast endpoints, WebSocket lifecycle, and log handling.

The source review does **not** replace runtime testing. Do not treat this branch as production-ready until the remaining checks in [TODO.md](TODO.md) have been completed against a staging WordPress site and the target AzuraCast instance.

## Configuration

All stations are managed in the WordPress `azns_settings` option through the plugin's administration screen. Station shortcodes and server URLs are not hardcoded into the plugin source.

See [`readme.txt`](readme.txt) for installation and shortcode details, and [`CHANGELOG.md`](CHANGELOG.md) for the audit changes.
