const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'front.js'), 'utf8');

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName;
        this.className = '';
        this.children = [];
        this._text = '';
    }

    append(...nodes) {
        nodes.forEach(node => {
            node.parentNode = this;
            this.children.push(node);
        });
    }

    replaceChildren(...nodes) {
        this.children.forEach(child => { child.parentNode = null; });
        this._text = '';
        this.children = [];
        this.append(...nodes);
    }

    setAttribute(name, value) {
        this[name] = value;
    }

    remove() {
        if (!this.parentNode) return;
        this.parentNode.children = this.parentNode.children.filter(child => child !== this);
        this.parentNode = null;
    }

    set textContent(value) {
        this._text = String(value ?? '');
        this.children = [];
    }

    get textContent() {
        return this._text + this.children.map(child => child.textContent || '').join('');
    }
}

function response(data) {
    return {
        ok: true,
        json: async () => data,
    };
}

function createHarness(fetchImpl, type = 'next', art = false, extraWidgets = []) {
    const sockets = [];
    const timers = [];
    let nextTimerId = 0;
    const definitions = [{ id: 'azns-test', type, art, shortcode: 'test_station' }, ...extraWidgets];
    const states = definitions.map((definition, index) => {
        const id = definition.id || 'azns-test-' + (index + 1);
        const shortcode = definition.shortcode || 'test_station';
        const target = new FakeElement('div');
        const status = new FakeElement('small');
        const widget = {
            id,
            dataset: { type: definition.type || 'next', limit: String(definition.limit || 10) },
            querySelector(selector) {
                if (selector === '.azns-next' || selector === '.azns-indicator' || selector === '.azns-current' || selector === '.azns-history') return target;
                if (selector === '.azns-status') return status;
                return null;
            },
        };
        return { id, shortcode, target, status, widget, art: definition.art ?? false, definition };
    });

    const document = {
        readyState: 'complete',
        createElement: tagName => new FakeElement(tagName),
        querySelectorAll: () => states.map(state => state.widget),
    };
    const configurations = {};
    states.forEach(state => {
        const definition = state.definition;
        configurations[state.id] = {
            server: definition.server || 'https://radio.example.test/radio-proxy',
            shortcode: state.shortcode,
            rest: definition.rest || '/wp-json/azns/v1/station/' + state.shortcode + '/',
            art: state.art,
        };
    });
    const window = {
        AZNS: configurations,
        addEventListener() {},
    };

    class FakeWebSocket {
        constructor(url) {
            this.url = url;
            this.sent = [];
            this.closed = false;
            sockets.push(this);
        }

        send(value) {
            this.sent.push(value);
        }

        close() {
            this.closed = true;
        }
    }

    const sandbox = {
        window,
        document,
        URL,
        WebSocket: FakeWebSocket,
        fetch: fetchImpl,
        setTimeout: (callback, delay) => {
            const timer = { id: ++nextTimerId, callback, delay, cleared: false };
            timers.push(timer);
            return timer.id;
        },
        clearTimeout: id => {
            const timer = timers.find(item => item.id === id);
            if (timer) timer.cleared = true;
        },
        console,
    };

    vm.runInNewContext(source, sandbox, { filename: 'front.js' });
    return {
        target: states[0].target,
        status: states[0].status,
        widget: states[0].widget,
        sockets,
        timers,
        states,
    };
}

function nextData(title, artist = 'Test Artist') {
    return {
        playing_next: {
            song: {
                artist,
                title,
                text: `${artist} - ${title}`,
                art: 'https://radio.example.test/art.jpg',
            },
        },
    };
}

async function flushPromises() {
    await new Promise(resolve => setImmediate(resolve));
    await Promise.resolve();
}

test('renders Playing Next from the initial Now Playing JSON snapshot', async () => {
    const harness = createHarness(async () => response(nextData('From JSON')));
    await flushPromises();

    assert.match(harness.target.textContent, /Test Artist - From JSON/);
    assert.equal(harness.sockets.length, 1);
    assert.equal(
        harness.sockets[0].url,
        'wss://radio.example.test/radio-proxy/api/live/nowplaying/websocket'
    );
});

test('updates Playing Next from an AzuraCast WebSocket publication', () => {
    const harness = createHarness(() => new Promise(() => {}));
    const socket = harness.sockets[0];

    socket.onopen();
    const subscription = JSON.parse(socket.sent[0]);
    assert.deepEqual(
        JSON.parse(JSON.stringify(subscription)),
        { subs: { 'station:test_station': { recover: true } } }
    );

    socket.onmessage({
        data: JSON.stringify({ pub: { data: { np: nextData('From WebSocket') } } }),
    });

    assert.match(harness.target.textContent, /Test Artist - From WebSocket/);
});

test('shows an explicit empty-queue state when playing_next is null', async () => {
    const harness = createHarness(async () => response({ playing_next: null }));
    await flushPromises();

    assert.match(harness.target.textContent, /No upcoming track is currently queued/);
});

test('does not let a delayed REST snapshot overwrite a newer WebSocket publication', async () => {
    let resolveFetch;
    const pendingFetch = new Promise(resolve => {
        resolveFetch = resolve;
    });
    const harness = createHarness(() => pendingFetch);

    harness.sockets[0].onmessage({
        data: JSON.stringify({ pub: { data: { np: nextData('Fresh WebSocket') } } }),
    });
    assert.match(harness.target.textContent, /Fresh WebSocket/);

    resolveFetch(response(nextData('Stale REST')));
    await flushPromises();

    assert.match(harness.target.textContent, /Fresh WebSocket/);
    assert.doesNotMatch(harness.target.textContent, /Stale REST/);
});

test('renders current listener count from the AzuraCast Now Playing payload', async () => {
    const harness = createHarness(async () => response({
        listeners: { current: 9, total: 12 },
    }), 'listeners');
    await flushPromises();

    assert.match(harness.target.textContent, /9 listening/);
    assert.doesNotMatch(harness.target.textContent, /12 listening/);
});

test('renders Live DJ and AutoDJ from the authoritative live flag', async () => {
    const live = createHarness(async () => response({
        live: { is_live: true, streamer_name: 'Test DJ' },
    }), 'broadcast');
    await flushPromises();
    assert.match(live.target.textContent, /Live DJ — Test DJ/);

    const autodj = createHarness(async () => response({
        live: { is_live: false, streamer_name: '' },
    }), 'broadcast');
    await flushPromises();
    assert.match(autodj.target.textContent, /AutoDJ/);
});

test('provides an accessible artwork fallback for missing, invalid and failed images', async () => {
    const missing = createHarness(async () => response({
        playing_next: { song: { artist: 'Test Artist', title: 'Missing Art' } },
    }), 'next', true);
    await flushPromises();
    assert.match(missing.target.textContent, /No artwork/);
    assert.equal(missing.target.children.some(child => child.tagName === 'img'), false);

    const invalid = createHarness(async () => response({
        playing_next: { song: { artist: 'Test Artist', title: 'Invalid Art', art: 'data:image/png;base64,abcd' } },
    }), 'next', true);
    await flushPromises();
    assert.match(invalid.target.textContent, /No artwork/);
    assert.equal(invalid.target.children.some(child => child.tagName === 'img'), false);

    const failed = createHarness(async () => response(nextData('Image Fails')), 'next', true);
    await flushPromises();
    const image = failed.target.children.find(child => child.tagName === 'img');
    const fallback = failed.target.children.find(child => child.className === 'azns-art-placeholder');
    assert.ok(image, 'A valid artwork URL should create an image element.');
    assert.ok(fallback, 'The fallback should remain visible while the image loads.');
    assert.equal(fallback.role, 'img');
    assert.equal(fallback['aria-label'], 'Album artwork unavailable');
    image.onerror();
    assert.equal(failed.target.children.includes(image), false);
    assert.equal(failed.target.children.includes(fallback), true);
});

test('removes the artwork fallback after a successful image load', async () => {
    const harness = createHarness(async () => response(nextData('Image Loads')), 'next', true);
    await flushPromises();
    const image = harness.target.children.find(child => child.tagName === 'img');
    const fallback = harness.target.children.find(child => child.className === 'azns-art-placeholder');
    assert.ok(image);
    assert.ok(fallback);
    image.onload();
    assert.equal(harness.target.children.includes(fallback), false);
    assert.equal(harness.target.children.includes(image), true);
});

test('refreshes the Now Playing snapshot and reconnects after the WebSocket closes', async () => {
    let fetchCount = 0;
    const harness = createHarness(async () => {
        fetchCount += 1;
        return response(nextData(`Snapshot ${fetchCount}`));
    });
    await flushPromises();

    assert.equal(fetchCount, 1);
    assert.equal(harness.sockets.length, 1);

    harness.sockets[0].onclose();
    const retryTimer = harness.timers.find(timer => !timer.cleared);
    assert.ok(retryTimer, 'A reconnect timer should be scheduled after the socket closes.');
    assert.equal(retryTimer.delay, 1000);

    retryTimer.callback();
    await flushPromises();

    assert.equal(fetchCount, 2, 'The REST snapshot should refresh during reconnection.');
    assert.equal(harness.sockets.length, 2, 'A new WebSocket connection should be created.');
    assert.match(harness.target.textContent, /Snapshot 2/);

    harness.sockets[1].onopen();
    assert.equal(harness.status.hidden, true, 'A recovered WebSocket should clear the stale reconnect warning.');
});

test('shows an unavailable status when the AzuraCast API cannot be reached', async () => {
    const harness = createHarness(async () => ({
        ok: false,
        status: 503,
        json: async () => ({ error: 'Service unavailable' }),
    }));
    await flushPromises();

    assert.equal(harness.status.textContent, 'Unable to load nowplaying');
    assert.equal(harness.status.hidden, false);
});


test('shares one REST snapshot and one WebSocket across components for the same station', async () => {
    let fetchCount = 0;
    const harness = createHarness(async () => {
        fetchCount += 1;
        return response({
            playing_next: { song: { artist: 'Shared Artist', title: 'Shared Snapshot' } },
            listeners: { current: 14 },
            live: { is_live: true, streamer_name: 'Shared DJ' },
            now_playing: { song: { artist: 'Current Artist', title: 'Current Track' } },
        });
    }, 'next', false, [
        { type: 'listeners' },
        { type: 'broadcast' },
        { type: 'now-playing' },
    ]);
    await flushPromises();

    assert.equal(fetchCount, 1, 'Widgets for one station should share a single initial REST request.');
    assert.equal(harness.sockets.length, 1, 'Widgets for one station should share a single WebSocket.');
    assert.match(harness.states[0].target.textContent, /Shared Artist — Shared Snapshot/);
    assert.match(harness.states[1].target.textContent, /14 listening/);
    assert.match(harness.states[2].target.textContent, /Live DJ — Shared DJ/);
    assert.match(harness.states[3].target.textContent, /Current Artist — Current Track/);

    harness.sockets[0].onmessage({
        data: JSON.stringify({
            pub: {
                data: {
                    np: {
                        playing_next: { song: { artist: 'Live Artist', title: 'Live Update' } },
                        listeners: { current: 21 },
                        live: { is_live: false },
                        now_playing: { song: { artist: 'Current Artist', title: 'Live Track' } },
                    },
                },
            },
        }),
    });

    assert.match(harness.states[0].target.textContent, /Live Artist — Live Update/);
    assert.match(harness.states[1].target.textContent, /21 listening/);
    assert.match(harness.states[2].target.textContent, /AutoDJ/);
    assert.match(harness.states[3].target.textContent, /Current Artist — Live Track/);
});

test('keeps separate live connections for different configured stations', async () => {
    let fetchCount = 0;
    const harness = createHarness(async () => {
        fetchCount += 1;
        return response(nextData('Station snapshot ' + fetchCount));
    }, 'next', false, [
        { type: 'listeners', shortcode: 'other_station' },
    ]);
    await flushPromises();

    assert.equal(fetchCount, 2);
    assert.equal(harness.sockets.length, 2);
    harness.sockets[0].onopen();
    harness.sockets[1].onopen();
    assert.deepEqual(
        JSON.parse(harness.sockets[0].sent[0]).subs,
        { 'station:test_station': { recover: true } }
    );
    assert.deepEqual(
        JSON.parse(harness.sockets[1].sent[0]).subs,
        { 'station:other_station': { recover: true } }
    );
});

test('keeps a shared WebSocket alive until the final component unsubscribes', async () => {
    const harness = createHarness(async () => response(nextData('Shared cleanup')), 'next', false, [
        { type: 'listeners' },
    ]);
    await flushPromises();

    assert.equal(harness.sockets.length, 1);
    harness.states[0].widget._aznsClose();
    assert.equal(harness.sockets[0].closed, false, 'One remaining component must keep the shared connection alive.');
    harness.states[1].widget._aznsClose();
    assert.equal(harness.sockets[0].closed, true, 'The connection should close after its last component unsubscribes.');
});


test('renders only the configured number of recent history entries', async () => {
    const harness = createHarness(async () => response({
        song_history: [
            { song: { artist: 'Artist One', title: 'First Track' } },
            { song: { artist: 'Artist Two', title: 'Second Track' } },
            { song: { artist: 'Artist Three', title: 'Third Track' } },
        ],
    }), 'history');
    harness.widget.dataset.limit = '2';
    await flushPromises();

    assert.match(harness.target.textContent, /Artist One — First Track/);
    assert.match(harness.target.textContent, /Artist Two — Second Track/);
    assert.doesNotMatch(harness.target.textContent, /Artist Three — Third Track/);
});
