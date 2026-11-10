# Changelog

## 1.4.0 — WordPress-native components and administration (development)

- Added eight dynamic Gutenberg blocks for Player, Now Playing, Playing Next, History, Podcasts, Schedule, Listener Count and Broadcast Mode.
- Made the Gutenberg History limit functional with a native `song_history` list while preserving the existing History shortcode's official AzuraCast iframe behavior.
- Added a compact Upcoming Show shortcode and Gutenberg block backed by AzuraCast's public Schedule endpoint; it displays the first future entry returned by AzuraCast, or the current entry when no future entry is available.
- Added an administrator-only station connection test with nonce and capability checks.
- Added per-station health history with last check and last successful check timestamps.
- Added form feedback for invalid/duplicate station rows and invalid default-station selection.
- Added a sanitized diagnostics JSON export that omits server URLs and custom CSS and redacts secrets/URLs in log entries.
- Added listener-count and Live DJ/AutoDJ components using the existing Now Playing JSON/WebSocket feed.
- Shared one initial Now Playing REST snapshot and one WebSocket connection per station across all live components, with isolated feeds for different stations and a fresh snapshot on reconnect.
- Added an accessible artwork fallback for missing URLs and failed image loads, plus polite atomic live regions for changing track and listener data.
- Added reconnect/offline frontend regression cases, PHP shortcode render/accessibility tests and PHP tests for Gutenberg registration/server-render callbacks.
- Added PHP CLI regression coverage for station configuration, URL validation, API resource allow-listing, endpoint construction, cache TTLs, malformed responses and REST proxy status handling.
- Added the official WordPress Plugin Check workflow for clean release archives (general, security, performance and accessibility categories).
- Tightened station URL validation to reject numeric-only hosts.

> This section describes development work on the development branch; it is not a published release. Live WordPress/AzuraCast integration testing remains part of the 1.4.0 release acceptance checklist.

## 1.3.4 — Playing Next reliability and automated checks

- Prevented a delayed Now Playing REST snapshot from overwriting a newer WebSocket publication.
- Prevented an older failed snapshot from replacing the live-feed state with an error message.
- Added Node.js regression tests for the Playing Next JSON/WebSocket flow.
- Added GitHub Actions PHP syntax checks for PHP 8.1 and 8.5, plus frontend syntax and behavior tests.

## 1.3.3 — Audit fixes

- Removed the built-in station seed; station entries are read exclusively from azns_settings.
- Hardened station URL parsing, malformed settings handling, and duplicate shortcode handling.
- Hardened administrator settings saves against malformed arrays and invalid station rows.
- Improved log-directory protection files for Apache 2.4+, legacy Apache, and IIS; documented the Nginx rule.
- Redacted credentials and secret-like values recursively in log context.
- Fixed Now Playing loading status remaining visible after a successful response.
- Improved artwork URL validation and WebSocket handling for installations served from a URL subdirectory.
- Reinitialized live widgets after browser back/forward-cache restoration.
- Disabled automatic HTTP redirect following for public proxy requests.

## 1.3.2

- Removed the “WebSocket connected” and “Now Playing data updated” status messages.

## 1.3.1 — WIP baseline

- Initial repository baseline imported from the AzuraCast Suite 1.3.1 package.
- Official AzuraCast public-page iframe embeds are used for Player, History, Podcasts, and Schedule.
- WordPress-rendered Now Playing and Playing Next components are included.
- Playing Next is intended to display only the queued track.

> A changelog entry records code intent; it does not replace live integration testing.
