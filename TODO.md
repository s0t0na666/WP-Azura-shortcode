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

## Architecture requirement: AzuraCast-rendered UI
- [ ] Make AzuraCast the renderer/source of truth for the player and all radio-facing UI elements. WordPress must not generate its own default/native player, Now Playing, Playing Next, artwork, listeners, history, podcast, or schedule UI.
- [ ] Keep WordPress responsible for station configuration, shortcode handling, and a lightweight responsive embed/container wrapper only. Render the actual radio interface from AzuraCast public pages/official embed views.
- [ ] Inventory the official AzuraCast 0.24.0 public pages and embed options for every component (player, current track, Playing Next/queue, artwork, listener count, history, podcasts, schedule). Use the supported AzuraCast page/embed wherever available.
- [ ] Specifically verify how Playing Next can be displayed using an AzuraCast-owned page/component. Do not silently retain a WordPress-rendered fallback; document any AzuraCast limitation and the supported alternative before implementation.
- [ ] Once equivalent AzuraCast-rendered output is confirmed, remove the redundant WordPress frontend rendering, custom metadata WebSocket UI, and REST proxy paths that are no longer needed.
- [ ] Preserve existing WordPress shortcode names and dynamic station selection where practical, so existing pages can continue to use their current shortcode interface.
- [ ] Verify responsive sizing, theme selection, and embed behavior for each AzuraCast component on desktop and mobile.

## Still requires runtime testing
- [ ] Run PHP syntax checks on PHP 8.1 and PHP 8.5.
- [ ] Run JavaScript syntax checks in supported browsers.
- [ ] Test shortcodes, settings saves, and iframe URLs in a clean WordPress installation.
- [ ] Test against the target AzuraCast 0.24.0 instance, including a reverse-proxy subdirectory.
- [ ] Verify log-directory access denial on Apache/LiteSpeed and IIS; install the Nginx rule where applicable.
- [ ] Build and install a fresh ZIP on a staging WordPress site.

Source review and OpenAPI path verification do not count as live integration tests.
