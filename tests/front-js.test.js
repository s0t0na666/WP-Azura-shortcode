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

function createHarness(fetchImpl, type = 'next', art = false) {
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
                art,
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
