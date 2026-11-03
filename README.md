# WP Azura Shortcode

WordPress plugin for embedding AzuraCast public pages and displaying radio components.

## Development status

Current audited source version: **1.3.3**.

The current branch has a mixed rendering model: Player, History, Podcasts, and Schedule use AzuraCast public-page embeds, while Now Playing and Playing Next are currently rendered by WordPress frontend code. The target architecture is to have AzuraCast render all radio-facing UI, with WordPress acting only as the station configuration and shortcode/embed wrapper.

The source has been reviewed against the AzuraCast 0.24.0 OpenAPI schema and public route definitions. Source review does **not** replace runtime testing. The plugin is not production-ready until the remaining checks in [TODO.md](TODO.md) have been completed against a staging WordPress site and the target AzuraCast instance.

## Configuration

All stations are managed in the WordPress `azns_settings` option through the plugin's administration screen. Station shortcodes and server URLs are not hardcoded into the plugin source.

See [`readme.txt`](readme.txt) for installation and shortcode details, and [`CHANGELOG.md`](CHANGELOG.md) for the audit changes.
