(() => {
    'use strict';

    const rows = document.getElementById('azns-rows');
    const add = document.getElementById('azns-add');
    const stationTemplate = document.getElementById('azns-station-template');
    const defaultStation = document.getElementById('azns-default');

    if (rows && add && stationTemplate && defaultStation) {
        let nextIndex = rows.children.length;

        function updateStations() {
            const previous = defaultStation.selectedOptions[0];
            const options = [];
            for (const card of rows.children) {
                const slug = card.querySelector('[data-station-slug]');
                const name = card.querySelector('[data-station-name]').value.trim();
                const value = slug.value.trim();
                card.querySelector('[data-station-title]').textContent = name || value || 'New station';
                if (!value) continue;

                const option = new Option(name ? name + ' (' + value + ')' : value, value);
                option.dataset.row = slug.name;
                options.push(option);
            }
            const selected = options.find(option => previous?.dataset.row
                ? option.dataset.row === previous.dataset.row
                : option.value === previous?.value);
            defaultStation.replaceChildren(...options);
            if (selected) selected.selected = true;
            refreshBadges();
        }

        function refreshBadges() {
            const selectedRow = defaultStation.selectedOptions[0]?.dataset.row;
            for (const card of rows.children) {
                const slug = card.querySelector('[data-station-slug]');
                card.querySelector('[data-station-default]').hidden = slug.name !== selectedRow;
                card.querySelector('.azns-remove').hidden = false;
            }
        }

        add.hidden = false;
        updateStations();
        defaultStation.addEventListener('change', refreshBadges);
        rows.addEventListener('input', event => {
            if (event.target.matches('[data-station-name], [data-station-slug]')) updateStations();
        });
        add.addEventListener('click', () => {
            const card = stationTemplate.content.firstElementChild.cloneNode(true);
            for (const element of [card, ...card.querySelectorAll('[name], [id]')]) {
                for (const attribute of ['name', 'id', 'aria-labelledby']) {
                    const value = element.getAttribute(attribute);
                    if (value?.includes('__INDEX__')) {
                        element.setAttribute(attribute, value.replace('__INDEX__', String(nextIndex)));
                    }
                }
            }
            nextIndex++;
            rows.append(card);
            updateStations();
            card.querySelector('[data-station-name]').focus();
        });
        rows.addEventListener('click', event => {
            const remove = event.target.closest('.azns-remove');
            if (!remove) return;
            const card = remove.closest('.azns-admin-station');
            const neighbor = card.nextElementSibling || card.previousElementSibling;
            card.remove();
            updateStations();
            (neighbor?.querySelector('.azns-remove') || add).focus();
        });
    }

    const station = document.getElementById('azns-shortcode-station');
    const status = document.getElementById('azns-copy-status');
    if (!station || !status) return;

    const codes = document.querySelectorAll('[data-shortcode]');
    station.addEventListener('change', () => {
        const attribute = station.value ? ' station="' + station.value + '"' : '';
        for (const code of codes) code.textContent = '[' + code.dataset.shortcode + attribute + ']';
        for (const button of document.querySelectorAll('[data-copy-shortcode]')) button.textContent = 'Copy';
        status.textContent = '';
    });

    for (const button of document.querySelectorAll('[data-copy-shortcode]')) {
        button.hidden = false;
        button.addEventListener('click', async () => {
            const code = button.previousElementSibling;
            const value = code.textContent;
            status.textContent = '';
            try {
                await navigator.clipboard.writeText(value);
                if (code.textContent === value) button.textContent = 'Copied';
                status.textContent = 'Copied ' + value;
            } catch {
                const selection = window.getSelection();
                if (selection) {
                    const range = document.createRange();
                    range.selectNodeContents(code);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                button.textContent = 'Copy manually';
                status.textContent = 'Automatic copying is unavailable. Copy the selected shortcode manually.';
            }
        });
    }
})();
