(() => {
    'use strict';

    if (!window.wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement, Fragment } = wp.element;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, RangeControl, Placeholder } = wp.components;

    const stationOptions = [
        { label: 'Use default station', value: '' },
        ...((window.AZNS_BLOCKS_CONFIG && Array.isArray(window.AZNS_BLOCKS_CONFIG.stations))
            ? window.AZNS_BLOCKS_CONFIG.stations
            : []),
    ];

    const definitions = [
        {
            name: 'azns/player',
            title: 'AzuraCast Player',
            description: 'Embed the official AzuraCast player.',
            icon: 'controls-volumeon',
            preview: 'The official AzuraCast player is rendered on the published page.',
        },
        {
            name: 'azns/now-playing',
            title: 'AzuraCast Now Playing',
            description: 'Show the current track and live DJ information.',
            icon: 'format-audio',
            preview: 'Current track information is rendered on the published page.',
        },
        {
            name: 'azns/playing-next',
            title: 'AzuraCast Playing Next',
            description: 'Show the next queued track from AzuraCast.',
            icon: 'playlist-audio',
            preview: 'The next queued track is rendered on the published page.',
        },
        {
            name: 'azns/history',
            title: 'AzuraCast History',
            description: 'Show recent tracks reported by AzuraCast.',
            icon: 'list-view',
            preview: 'A WordPress-rendered list of recent station tracks is shown on the published page.',
            hasLimit: true,
        },
        {
            name: 'azns/podcasts',
            title: 'AzuraCast Podcasts',
            description: 'Embed the station podcast listing.',
            icon: 'microphone',
            preview: 'The AzuraCast podcast listing is rendered on the published page.',
        },
        {
            name: 'azns/schedule',
            title: 'AzuraCast Schedule',
            description: 'Embed the station broadcast schedule.',
            icon: 'calendar',
            preview: 'The official AzuraCast schedule is rendered on the published page.',
        },
        {
            name: 'azns/upcoming-show',
            title: 'AzuraCast Upcoming Show',
            description: 'Show the next scheduled streamer or playlist from AzuraCast.',
            icon: 'clock',
            preview: 'The next schedule entry reported by AzuraCast is rendered on the published page.',
        },
        {
            name: 'azns/listeners',
            title: 'AzuraCast Listener Count',
            description: 'Show the current listener count reported by AzuraCast.',
            icon: 'groups',
            preview: 'The current listener count is rendered on the published page.',
        },
        {
            name: 'azns/broadcast-mode',
            title: 'AzuraCast Broadcast Mode',
            description: 'Show whether the station is using a live DJ or AutoDJ.',
            icon: 'radio',
            preview: 'Live DJ or AutoDJ mode is rendered on the published page.',
        },
    ];

    definitions.forEach(definition => {
        registerBlockType(definition.name, {
            title: definition.title,
            description: definition.description,
            icon: definition.icon,
            category: 'widgets',
            attributes: {
                station: { type: 'string', default: '' },
                limit: { type: 'number', default: 10 },
            },
            edit: props => {
                const selectedStation = stationOptions.find(option => option.value === props.attributes.station);
                const stationLabel = selectedStation ? selectedStation.label : 'Use default station';
                const controls = [
                    createElement(SelectControl, {
                        key: 'station',
                        label: 'AzuraCast station',
                        value: props.attributes.station || '',
                        options: stationOptions,
                        help: 'Choose a configured station or use the default station from plugin settings.',
                        onChange: value => props.setAttributes({ station: value }),
                    }),
                ];

                if (definition.hasLimit) {
                    controls.push(createElement(RangeControl, {
                        key: 'limit',
                        label: 'Maximum history entries',
                        value: props.attributes.limit || 10,
                        min: 1,
                        max: 50,
                        help: 'AzuraCast may return fewer entries according to the station API history setting.',
                        onChange: value => props.setAttributes({ limit: value }),
                    }));
                }

                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(PanelBody, { title: 'AzuraCast settings', initialOpen: true }, ...controls)
                    ),
                    createElement(
                        'div',
                        useBlockProps(),
                        createElement(Placeholder, {
                            icon: definition.icon,
                            label: definition.title,
                            instructions: definition.preview,
                        }, createElement('p', null, 'Station: ' + stationLabel))
                    )
                );
            },
            save: () => null,
        });
    });
})();
