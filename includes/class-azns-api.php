<?php
if (!defined('ABSPATH')) { exit; }

final class AZNS_API {
    private $stations;

    public function __construct($stations) { $this->stations = $stations; }

    public function fetch($station, $kind = 'nowplaying', $podcast_id = '') {
        $allowed = ['nowplaying', 'podcasts', 'schedule', 'podcast_episodes'];
        if (!in_array($kind, $allowed, true)) { return new WP_Error('azns_kind', 'Invalid resource'); }

        // The supplied 0.24.0 OpenAPI StationIdRequired schema accepts integer OR string values.
        // Use the station shortcode consistently; it is also the documented Now Playing identifier.
        $station_id = rawurlencode($station['shortcode']);
        if ($kind === 'nowplaying') {
            $path = '/api/nowplaying/' . rawurlencode($station['shortcode']);
        } elseif ($kind === 'podcasts') {
            // Public podcast listing, rather than the authenticated station-management endpoint.
            $path = '/api/station/' . $station_id . '/public/podcasts';
        } elseif ($kind === 'podcast_episodes') {
            $podcast_id = trim((string) $podcast_id);
            if ($podcast_id === '') { return new WP_Error('azns_podcast_id', 'Podcast ID is required.'); }
            $path = '/api/station/' . $station_id . '/public/podcast/' . rawurlencode($podcast_id) . '/episodes';
        } else {
            $path = '/api/station/' . $station_id . '/schedule';
        }

        $cache_key = 'azns_' . md5($station['url'] . $path);
        $cached = get_transient($cache_key);
        if ($cached !== false) { return $cached; }

        $headers = ['Accept' => 'application/json'];
        $response = wp_remote_get($station['url'] . $path, [
            'timeout' => 12,
            'redirection' => 2,
            'sslverify' => true,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            AZNS_Logger::log('error', 'API request failed', ['station' => $station['shortcode'], 'resource' => $kind, 'error' => $response->get_error_message()]);
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            AZNS_Logger::log('warning', 'Invalid API response', ['station' => $station['shortcode'], 'resource' => $kind, 'http' => $status]);
            return new WP_Error('azns_api', 'AzuraCast API returned an invalid response.');
        }

        $ttl = ($kind === 'nowplaying') ? 5 : 300;
        set_transient($cache_key, $data, $ttl);
        return $data;
    }

    public function routes() {
        register_rest_route('azns/v1', '/station/(?P<shortcode>[a-z0-9_-]+)/(?P<resource>nowplaying|podcasts|schedule|podcast-episodes)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function ($request) {
                $station = $this->stations->get($request['shortcode']);
                if (!$station) { return new WP_Error('azns_station', 'Station not configured', ['status' => 404]); }
                $podcast_id = $request->get_param('podcast_id');
                if ($request['resource'] === 'podcast-episodes' && !$podcast_id) {
                    return new WP_Error('azns_podcast_id', 'Podcast ID is required.', ['status' => 400]);
                }
                $kind = ($request['resource'] === 'podcast-episodes') ? 'podcast_episodes' : $request['resource'];
                $data = $this->fetch($station, $kind, $podcast_id);
                return is_wp_error($data)
                    ? new WP_REST_Response(['error' => $data->get_error_message()], 502)
                    : rest_ensure_response($data);
            },
            'args' => [
                'podcast_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }
}
