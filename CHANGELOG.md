# Changelog

## 1.3.4 — Playing Next reliability and automated checks

- Prevented a delayed Now Playing REST snapshot from overwriting a newer WebSocket publication.
- Prevented an older failed snapshot from replacing the live-feed state with an error message.
- Added Node.js regression tests for the Playing Next JSON/WebSocket flow.
- Added GitHub Actions PHP syntax checks for PHP 8.1 and 8.5, plus frontend syntax and behavior tests.

## 1.3.3 — Audit fixes

- Removed the built-in station seed; station entries are read exclusively from `azns_settings`.
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
