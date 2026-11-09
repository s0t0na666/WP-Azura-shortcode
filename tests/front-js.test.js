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
        this.children.push(...nodes);
    }

    replaceChildren(...nodes) {
        this._text = '';
        this.children = [...nodes];
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

function createHarness(fetchImpl, type = 'next') {
    const sockets = [];
    const target = new FakeElement('div');
    const widget = {
        id: 'azns-test',
        dataset: { type, limit: '10' },
        querySelector(selector) {
            if (selector === '.azns-next' || selector === '.azns-indicator' || selector === '.azns-current') return target;
            if (selector === '.azns-status') return null;
            return null;
        },
    };

    const document = {
        readyState: 'complete',
        createElement: tagName => new FakeElement(tagName),
        querySelectorAll: () => [widget],
    };
    const window = {
        AZNS: {
            'azns-test': {
                server: 'https://radio.example.test/radio-proxy',
                shortcode: 'test_station',
                rest: '/wp-json/azns/v1/station/test_station/',
                art: false,
            },
        },
        addEventListener() {},
    };

    class FakeWebSocket {
        constructor(url) {
            this.url = url;
            this.sent = [];
            sockets.push(this);
        }

        send(value) {
            this.sent.push(value);
        }

        close() {}
    }

    const sandbox = {
        window,
        document,
        URL,
        WebSocket: FakeWebSocket,
        fetch: fetchImpl,
        setTimeout: () => 1,
        clearTimeout() {},
        console,
    };

    vm.runInNewContext(source, sandbox, { filename: 'front.js' });
    return { target, sockets, widget };
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
