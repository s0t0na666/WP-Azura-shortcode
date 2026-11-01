=== AzuraCast Suite ===
Contributors: s0t0na666
Tags: azuracast, radio, player, streaming, shortcode
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.3.2
License: GPL-2.0-or-later

Embeds AzuraCast public pages and provides WordPress components for now playing, Playing Next, playback history, podcasts and schedule.

== Description ==

AzuraCast Suite connects a WordPress site to one or more AzuraCast stations.

Public AzuraCast pages are embedded for the player, playback history, podcasts and schedule. WordPress-rendered components provide now playing and Playing Next information using the AzuraCast API and live WebSocket feed.

== Installation ==

1. Upload the `azuracast-native-suite` plugin folder to `/wp-content/plugins/`, or upload the plugin ZIP through the WordPress Plugins screen.
2. Activate **AzuraCast Suite**.
3. Open the **AzuraCast** menu in the WordPress administration area.
4. Configure the station shortcode and AzuraCast server URL, then save the settings.
5. Add the required shortcode to a page or post.

== Shortcodes ==

* `[azura_player station="mugadavanje"]`
* `[azura_nowplaying station="mugadavanje"]`
* `[azura_next_track station="mugadavanje"]`
* `[azura_history station="mugadavanje" limit="10"]`
* `[azura_podcasts station="mugadavanje"]`
* `[azura_schedule station="mugadavanje"]`

Legacy Playing Next aliases are also supported:

* `[azuracast_playing_next]`
* `[azura_playing_next]`

== Configuration ==

Configure each station with its station shortcode, display name, AzuraCast server URL, public-page theme (dark or light), artwork preference and optional custom CSS.

The plugin uses public AzuraCast endpoints and does not require an API key in the browser.

== Changelog ==

= 1.3.2 =
* Removed the “WebSocket connected” and “Now Playing data updated” status messages.
