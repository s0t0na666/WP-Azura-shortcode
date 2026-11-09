=== AzuraCast Suite ===
Contributors: s0t0na666
Tags: azuracast, radio, player, streaming, shortcode
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.3.4
License: GPL-2.0-or-later

Embeds AzuraCast public pages and provides WordPress components for now playing, Playing Next, playback history, podcasts and schedule.

== Description ==

AzuraCast Suite connects a WordPress site to one or more AzuraCast stations.

Public AzuraCast pages are embedded for the player, playback history, podcasts and schedule. WordPress-rendered components provide now playing and Playing Next information using public AzuraCast API endpoints and the live WebSocket feed.

Stations are configured in the plugin settings and stored in the `azns_settings` WordPress option. The plugin does not require a station to be hardcoded into the source.

== Installation ==

1. Upload the `azuracast-native-suite` plugin folder to `/wp-content/plugins/`, or upload the plugin ZIP through the WordPress Plugins screen.
2. Activate **AzuraCast Suite**.
3. Open the **AzuraCast** menu in the WordPress administration area.
4. Add each station using its station shortcode and AzuraCast server URL, then save.
5. Select the default station and add the required shortcode to a page or post.

== Shortcodes ==

Replace `your_station_shortcode` with a shortcode configured in the plugin settings.

* `[azura_player station="your_station_shortcode"]`
* `[azura_nowplaying station="your_station_shortcode"]`
* `[azura_next_track station="your_station_shortcode"]`
* `[azura_history station="your_station_shortcode" limit="10"]`
* `[azura_podcasts station="your_station_shortcode"]`
* `[azura_schedule station="your_station_shortcode"]`

Legacy Playing Next aliases are also supported:

* `[azuracast_playing_next]`
* `[azura_playing_next]`

== Configuration ==

Configure each station with its shortcode, display name, AzuraCast server URL, public-page theme (dark or light), artwork preference and optional custom CSS.

The plugin uses public AzuraCast endpoints and does not require an API key in the browser. The station URL may include a reverse-proxy subdirectory.

== Logs ==

Logs are stored in `wp-content/uploads/azuracast-logs`. Apache/LiteSpeed and IIS protection files are generated automatically. Nginx does not read `.htaccess`; add a deny rule for the actual uploads URL path. For the default uploads path:

```nginx
location ^~ /wp-content/uploads/azuracast-logs/ {
    deny all;
}
```

== Changelog ==

= 1.3.4 =
* Prevented a delayed Now Playing REST snapshot from overwriting a newer WebSocket update.
* Added automated frontend regression tests and PHP syntax-check CI for the development branch.

= 1.3.3 =
* Hardened settings validation, logging and public API proxy behavior.
* Fixed Now Playing status and browser back/forward-cache reconnection behavior.
* Removed the built-in station default; configured stations remain dynamic.

= 1.3.2 =
* Removed WebSocket connected and metadata-updated status messages.
