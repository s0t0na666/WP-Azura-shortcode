const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'blocks.js'), 'utf8');

function createHarness() {
    const registered = {};
    const wp = {
        blocks: {
            registerBlockType: (name, settings) => { registered[name] = settings; },
        },
        element: {
            Fragment: 'Fragment',
            createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
        },
        blockEditor: {
            InspectorControls: 'InspectorControls',
            useBlockProps: () => ({ className: 'wp-block-azns' }),
        },
        components: {
            PanelBody: 'PanelBody',
            SelectControl: 'SelectControl',
            RangeControl: 'RangeControl',
            Placeholder: 'Placeholder',
        },
    };
    const window = {
        wp,
        AZNS_BLOCKS_CONFIG: {
            stations: [{ label: 'Mugadavanje (mugadavanje)', value: 'mugadavanje' }],
        },
    };
    vm.runInNewContext(source, { window, wp }, { filename: 'blocks.js' });
    return registered;
}

test('registers all eight dynamic AzuraCast blocks', () => {
    const registered = createHarness();
    assert.deepEqual(Object.keys(registered).sort(), [
        'azns/broadcast-mode',
        'azns/history',
        'azns/listeners',
        'azns/now-playing',
        'azns/player',
        'azns/playing-next',
        'azns/podcasts',
        'azns/schedule',
    ]);
    for (const block of Object.values(registered)) {
        assert.equal(block.category, 'widgets');
        assert.equal(block.save(), null);
        assert.equal(block.attributes.station.default, '');
    }
});

test('history block exposes a configurable entry limit', () => {
    const registered = createHarness();
    const history = registered['azns/history'];
    const tree = history.edit({
        attributes: { station: 'mugadavanje', limit: 15 },
        setAttributes() {},
    });
    assert.equal(tree.type, 'Fragment');
    const inspector = tree.children[0];
    const panel = inspector.children[0];
    assert.equal(panel.type, 'PanelBody');
    assert.equal(panel.props.title, 'AzuraCast settings');
    assert.equal(panel.children.some(child => child.type === 'RangeControl'), true);
});

test('non-history blocks do not expose the history limit control', () => {
    const registered = createHarness();
    const player = registered['azns/player'];
    const tree = player.edit({
        attributes: { station: '', limit: 10 },
        setAttributes() {},
    });
    const panel = tree.children[0].children[0];
    assert.equal(panel.children.some(child => child.type === 'RangeControl'), false);
});
