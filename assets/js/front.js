(() => {
    'use strict';
    const configs = window.AZNS || {};
    const make = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = String(text ?? '');
        return element;
    };
    const trackLabel = song => {
        if (!song) return 'No track information available';
        if (song.text) return song.text;
        const artist = (song.artist || '').trim();
        const title = (song.title || '').trim();
        if (!artist) return title || 'Unknown track';
        if (!title || title.toLowerCase().includes(artist.toLowerCase())) return title || artist;
        return `${artist} — ${title}`;
    };

    function renderTrack(target, row, showArt) {
        target.replaceChildren();
        const song = row?.song;
        if (!song) {
            target.append(make('span', '', 'No track available'));
            return;
        }
        const artUrl = (typeof song.art === 'string') ? song.art.trim() : '';
        if (showArt && /^https?:\/\//i.test(artUrl)) {
            const image = make('img', 'azns-art');
            image.src = artUrl;
            image.alt = 'Album artwork';
            image.loading = 'lazy';
            image.referrerPolicy = 'no-referrer';
            image.onerror = () => image.remove();
            target.append(image);
        }
        const meta = make('div', 'azns-track-meta');
        meta.append(make('strong', 'azns-title', trackLabel(song)));
        target.append(meta);
    }

    function renderPodcastList(widget, podcasts, config) {
        const target = widget.querySelector('.azns-podcasts');
        target.replaceChildren();
        if (!Array.isArray(podcasts) || podcasts.length === 0) {
            target.append(make('p', '', 'No public podcasts are available.'));
            return;
        }
        podcasts.forEach(podcast => {
            const card = make('article', 'azns-item');
            card.append(make('strong', '', podcast.title || 'Untitled podcast'));
            if (podcast.description_short || podcast.description) {
                card.append(make('p', '', podcast.description_short || podcast.description));
            }
            if (podcast.author) card.append(make('small', '', podcast.author));
            const episodes = make('div', 'azns-episodes');
            episodes.append(make('small', '', 'Loading episodes…'));
            card.append(episodes);
            target.append(card);

            if (!podcast.id) {
                episodes.replaceChildren(make('small', '', 'Episode listing unavailable: podcast ID is missing.'));
                return;
            }
            const url = config.rest + 'podcast-episodes?podcast_id=' + encodeURIComponent(podcast.id);
            fetch(url).then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            }).then(items => {
                episodes.replaceChildren();
                if (!Array.isArray(items) || items.length === 0) {
                    episodes.append(make('small', '', 'No published episodes.'));
                    return;
                }
                const list = make('ol', 'azns-episode-list');
                items.forEach(episode => {
                    const entry = make('li', 'azns-episode');
                    entry.append(make('strong', '', episode.title || 'Untitled episode'));
                    if (episode.description_short || episode.description) {
                        entry.append(make('p', '', episode.description_short || episode.description));
                    }
                    const rawPublishedAt = episode.publish_at;
                    const publishedAt = Number(rawPublishedAt);
                    const publishedDate = new Date(publishedAt * 1000);
                    if (rawPublishedAt !== null && rawPublishedAt !== undefined && rawPublishedAt !== ''
                        && Number.isFinite(publishedAt) && publishedAt > 0 && Number.isFinite(publishedDate.getTime())) {
                        const time = make('time', '');
                        time.dateTime = publishedDate.toISOString();
                        time.textContent = publishedDate.toLocaleDateString();
                        entry.append(time);
                    }
                    // The 0.24.0 schema exposes media metadata/path but does not define a public media URL field.
                    // Do not synthesize an audio URL from a storage path.
                    list.append(entry);
                });
                episodes.append(list);
            }).catch(() => {
                episodes.replaceChildren(make('small', '', 'Unable to load podcast episodes.'));
            });
        });
    }

    function renderSchedule(widget, entries) {
        const target = widget.querySelector('.azns-schedule');
        target.replaceChildren();
        if (!Array.isArray(entries) || entries.length === 0) {
            target.append(make('p', '', 'No schedule entries available.'));
            return;
        }
        entries.forEach(item => {
            const card = make('article', 'azns-item');
            card.append(make('strong', '', item.title || item.name || 'Untitled schedule entry'));
            if (item.description) card.append(make('p', '', item.description));
            if (item.start) {
                const time = make('time', '');
                time.dateTime = item.start;
                time.textContent = item.start;
                card.append(time);
            }
            if (item.is_now) card.append(make('small', 'azns-live', 'Happening now'));
            target.append(card);
        });
    }

    function init(widget) {
        const config = configs[widget.id];
        if (!config) return;
        const type = widget.dataset.type;
        const status = widget.querySelector('.azns-status');
        const limit = Number(widget.dataset.limit) || 10;
        let active = true;
        let socket = null;
        let retryTimer = null;
        let retryDelay = 1000;

        const setStatus = message => {
            if (!status) return;
            status.textContent = message;
            status.hidden = !message;
        };
        const applyNowPlaying = data => {
            if (!data || typeof data !== 'object') return;
            setStatus('');
            if (type === 'history') {
                const list = widget.querySelector('.azns-history');
                list.replaceChildren();
                (Array.isArray(data.song_history) ? data.song_history : []).slice(0, limit).forEach(row => {
                    list.append(make('li', '', trackLabel(row.song)));
                });
                if (!list.children.length) list.append(make('li', '', 'No recent tracks'));
            } else if (type === 'next') {
                renderTrack(widget.querySelector('.azns-next'), data.playing_next, config.art);
            } else {
                const target = widget.querySelector('.azns-current');
                renderTrack(target, data.now_playing, config.art);
                if (data.listeners) {
                    target.append(make('small', 'azns-listeners', `${data.listeners.current ?? data.listeners.total ?? 0} listening`));
                }
                if (data.live?.is_live) {
                    target.append(make('small', 'azns-live', `Live: ${data.live.streamer_name || 'DJ'}`));
                }
            }
        };

        const load = async resource => {
            try {
                const response = await fetch(config.rest + resource, { credentials: 'same-origin' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                if (!active) return;
                if (resource === 'nowplaying') applyNowPlaying(data);
                else if (resource === 'podcasts') renderPodcastList(widget, data, config);
                else if (resource === 'schedule') renderSchedule(widget, data);
            } catch (error) {
                setStatus(`Unable to load ${resource}`);
            }
        };

        if (type === 'podcasts' || type === 'schedule') {
            load(type);
            return;
        }

        load('nowplaying');
        const handlePayload = payload => {
            const data = payload?.data;
            if (data?.np) applyNowPlaying(data.np);
        };
        const retry = () => {
            if (!active) return;
            setStatus('Connection lost; reconnecting…');
            clearTimeout(retryTimer);
            retryTimer = setTimeout(() => {
                load('nowplaying');
                connect();
            }, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 30000);
        };
        const connect = () => {
            if (!active) return;
            try {
                const server = new URL(config.server);
                const protocol = server.protocol === 'https:' ? 'wss:' : 'ws:';
                const basePath = server.pathname.replace(/\/+$/, '');
                socket = new WebSocket(`${protocol}//${server.host}${basePath}/api/live/nowplaying/websocket`);
            } catch (error) {
                retry();
                return;
            }
            socket.onopen = () => {
                retryDelay = 1000;
                const subscriptions = {};
                subscriptions[`station:${config.shortcode}`] = { recover: true };
                socket.send(JSON.stringify({ subs: subscriptions }));
            };
            socket.onmessage = event => {
                try {
                    const message = JSON.parse(event.data);
                    if (message.pub) handlePayload(message.pub);
                    if (Array.isArray(message.connect?.data)) {
                        message.connect.data.forEach(handlePayload);
                    } else {
                        Object.values(message.connect?.subs || {}).forEach(subscription => {
                            (subscription.publications || []).forEach(handlePayload);
                        });
                    }
                } catch (error) {
                    // Ignore malformed or non-JSON frames; AzuraCast also sends empty ping frames.
                }
            };
            socket.onerror = () => setStatus('WebSocket reconnecting');
            socket.onclose = retry;
        };
        connect();
        widget._aznsClose = () => {
            active = false;
            clearTimeout(retryTimer);
            if (socket) {
                socket.onclose = null;
                socket.onerror = null;
                socket.close();
            }
            widget._aznsClose = null;
        };
    }

    const start = () => document.querySelectorAll('.azns[data-type]').forEach(widget => {
        if (typeof widget._aznsClose !== 'function') init(widget);
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
    window.addEventListener('pagehide', () => {
        document.querySelectorAll('.azns[data-type]').forEach(widget => widget._aznsClose?.());
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) start();
    });
})();
