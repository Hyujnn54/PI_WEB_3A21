const LEAFLET_JS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const LEAFLET_CSS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const DEFAULT_CENTER = [36.8065, 10.1815];
const DEFAULT_ZOOM = 12;
const MAX_LOCATION_LENGTH = 120;

function ensureLeafletStylesheet() {
    if (document.querySelector('link[data-tb-leaflet-css="1"]')) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = LEAFLET_CSS_URL;
    link.crossOrigin = '';
    link.dataset.tbLeafletCss = '1';
    document.head.appendChild(link);
}

function ensureMapPickerStyles() {
    if (document.getElementById('tbInterviewMapPickerStyles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'tbInterviewMapPickerStyles';
    style.textContent = ''
        + '#tbInterviewMapPickerMap { min-height: 360px; border-radius: 10px; border: 1px solid #dbe5f5; }\n'
        + '#tbInterviewMapPickerResults { max-height: 220px; overflow-y: auto; }\n'
        + '.tb-map-picker-result { text-align: left; white-space: normal; }\n';
    document.head.appendChild(style);
}

function loadLeafletScript() {
    if (window.L) {
        return Promise.resolve(window.L);
    }

    return new Promise(function (resolve, reject) {
        const existingScript = document.querySelector('script[data-tb-leaflet-js="1"]');
        if (existingScript) {
            existingScript.addEventListener('load', function () { resolve(window.L); }, { once: true });
            existingScript.addEventListener('error', function () { reject(new Error('Unable to load Leaflet.')); }, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = LEAFLET_JS_URL;
        script.async = true;
        script.dataset.tbLeafletJs = '1';
        script.addEventListener('load', function () {
            if (window.L) {
                resolve(window.L);
            } else {
                reject(new Error('Leaflet did not initialize correctly.'));
            }
        }, { once: true });
        script.addEventListener('error', function () {
            reject(new Error('Unable to load Leaflet.'));
        }, { once: true });
        document.head.appendChild(script);
    });
}

function sanitizeLabel(value) {
    return String(value || '')
        .replace(/[^A-Za-z0-9 ,./#()\-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function formatLocationValue(rawLabel, lat, lng) {
    const safeLat = Number(lat);
    const safeLng = Number(lng);
    const coords = safeLat.toFixed(6) + ',' + safeLng.toFixed(6);
    const suffix = ' (' + coords + ')';

    let label = sanitizeLabel(rawLabel);
    if (label === '') {
        label = 'Pinned location';
    }

    const maxLabelLength = Math.max(10, MAX_LOCATION_LENGTH - suffix.length);
    if (label.length > maxLabelLength) {
        label = label.slice(0, maxLabelLength).trim();
    }

    return label + suffix;
}

function extractLabelFromLocation(value) {
    const text = String(value || '').trim();
    const index = text.lastIndexOf('(');
    if (index <= 0) {
        return text;
    }

    return text.slice(0, index).trim();
}

function parseCoordinatesFromLocation(value) {
    const text = String(value || '').trim();
    const match = text.match(/\((-?\d{1,3}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)\)\s*$/);
    if (!match) {
        return null;
    }

    const lat = Number(match[1]);
    const lng = Number(match[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        return null;
    }

    return { lat: lat, lng: lng };
}

function ensureMapPickerModal() {
    let modal = document.getElementById('tbInterviewMapPickerModal');
    if (modal) {
        return modal;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = ''
        + '<div class="modal fade" id="tbInterviewMapPickerModal" tabindex="-1" aria-hidden="true">'
        + '  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">'
        + '    <div class="modal-content tb-admin-card tb-modal-shell">'
        + '      <div class="modal-header tb-modal-header">'
        + '        <h5 class="modal-title"><i class="ti ti-map-pin me-1"></i>Select Interview Location</h5>'
        + '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
        + '      </div>'
        + '      <div class="modal-body">'
        + '        <div class="row g-3">'
        + '          <div class="col-lg-4">'
        + '            <label class="form-label">Search place</label>'
        + '            <div class="input-group mb-2">'
        + '              <input type="text" class="form-control" id="tbInterviewMapPickerSearchInput" placeholder="Google Maps HQ">'
        + '              <button type="button" class="btn btn-outline-primary" id="tbInterviewMapPickerSearchBtn">Search</button>'
        + '            </div>'
        + '            <div class="small text-secondary mb-2">Click a result or click directly on the map.</div>'
        + '            <div id="tbInterviewMapPickerResults" class="list-group"></div>'
        + '          </div>'
        + '          <div class="col-lg-8">'
        + '            <div id="tbInterviewMapPickerMap"></div>'
        + '            <div class="small text-secondary mt-2" id="tbInterviewMapPickerSelection">No location selected.</div>'
        + '          </div>'
        + '        </div>'
        + '      </div>'
        + '      <div class="modal-footer">'
        + '        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>'
        + '        <button type="button" class="btn btn-primary" id="tbInterviewMapPickerConfirm" disabled>Use this location</button>'
        + '      </div>'
        + '    </div>'
        + '  </div>'
        + '</div>';

    document.body.appendChild(wrapper.firstElementChild);
    modal = document.getElementById('tbInterviewMapPickerModal');
    return modal;
}

async function searchPlaces(query) {
    const response = await fetch(
        'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=8&q=' + encodeURIComponent(query),
        {
            headers: {
                Accept: 'application/json',
                'Accept-Language': 'en'
            }
        }
    );

    if (!response.ok) {
        throw new Error('Search request failed.');
    }

    const data = await response.json();
    return Array.isArray(data) ? data : [];
}

async function reverseLookup(lat, lng) {
    const response = await fetch(
        'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(String(lat)) + '&lon=' + encodeURIComponent(String(lng)),
        {
            headers: {
                Accept: 'application/json',
                'Accept-Language': 'en'
            }
        }
    );

    if (!response.ok) {
        throw new Error('Reverse lookup failed.');
    }

    const data = await response.json();
    return data && data.display_name ? String(data.display_name) : 'Pinned location';
}

export function createInterviewMapPickerService() {
    let leafletPromise = null;
    let state = null;

    function ensureLeaflet() {
        ensureLeafletStylesheet();
        ensureMapPickerStyles();
        if (!leafletPromise) {
            leafletPromise = loadLeafletScript();
        }

        return leafletPromise;
    }

    function getState() {
        if (state) {
            return state;
        }

        const modalEl = ensureMapPickerModal();
        const searchInput = document.getElementById('tbInterviewMapPickerSearchInput');
        const searchButton = document.getElementById('tbInterviewMapPickerSearchBtn');
        const resultsRoot = document.getElementById('tbInterviewMapPickerResults');
        const selectionRoot = document.getElementById('tbInterviewMapPickerSelection');
        const confirmButton = document.getElementById('tbInterviewMapPickerConfirm');
        const mapRoot = document.getElementById('tbInterviewMapPickerMap');

        state = {
            modalEl: modalEl,
            bootstrapModal: null,
            map: null,
            marker: null,
            mapRoot: mapRoot,
            mapInitFailed: false,
            mapInitAttempted: false,
            activeInput: null,
            currentResults: [],
            selected: null,
            searchInput: searchInput,
            searchButton: searchButton,
            resultsRoot: resultsRoot,
            selectionRoot: selectionRoot,
            confirmButton: confirmButton,
        };

        if (window.bootstrap && window.bootstrap.Modal) {
            state.bootstrapModal = new window.bootstrap.Modal(modalEl);
        }

        modalEl.addEventListener('shown.bs.modal', function () {
            if (state && state.map) {
                window.setTimeout(function () {
                    state.map.invalidateSize();
                }, 50);
            }
        });

        if (searchButton) {
            searchButton.addEventListener('click', function () {
                runSearch();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    runSearch();
                }
            });
        }

        if (resultsRoot) {
            resultsRoot.addEventListener('click', function (event) {
                const target = event.target instanceof HTMLElement ? event.target.closest('[data-map-result-index]') : null;
                if (!target) {
                    return;
                }

                const index = Number(target.getAttribute('data-map-result-index'));
                if (!Number.isFinite(index) || index < 0 || index >= state.currentResults.length) {
                    return;
                }

                const place = state.currentResults[index];
                selectLocation(place.display_name || place.name || 'Pinned location', Number(place.lat), Number(place.lon), true);
            });
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                applySelectionToInput();
            });
        }

        return state;
    }

    function renderResults(places) {
        const localState = getState();
        localState.currentResults = places;

        if (!localState.resultsRoot) {
            return;
        }

        if (places.length === 0) {
            localState.resultsRoot.innerHTML = '<div class="small text-secondary">No results found.</div>';
            return;
        }

        localState.resultsRoot.innerHTML = places.map(function (place, index) {
            const label = sanitizeLabel(place.display_name || place.name || 'Pinned location');
            const lat = Number(place.lat);
            const lon = Number(place.lon);
            const preview = Number.isFinite(lat) && Number.isFinite(lon)
                ? lat.toFixed(5) + ', ' + lon.toFixed(5)
                : '';

            return ''
                + '<button type="button" class="list-group-item list-group-item-action tb-map-picker-result" data-map-result-index="' + String(index) + '">'
                + '  <div class="fw-semibold">' + label + '</div>'
                + '  <div class="small text-secondary">' + preview + '</div>'
                + '</button>';
        }).join('');
    }

    function renderSelection() {
        const localState = getState();

        if (localState.selectionRoot) {
            if (!localState.selected) {
                localState.selectionRoot.textContent = 'No location selected.';
            } else {
                localState.selectionRoot.textContent = 'Selected: ' + localState.selected.formatted;
            }
        }

        if (localState.confirmButton) {
            localState.confirmButton.disabled = !localState.selected;
        }
    }

    function selectLocation(label, lat, lng, centerMap) {
        const localState = getState();
        const safeLat = Number(lat);
        const safeLng = Number(lng);
        if (!Number.isFinite(safeLat) || !Number.isFinite(safeLng)) {
            return;
        }

        localState.selected = {
            label: sanitizeLabel(label),
            lat: safeLat,
            lng: safeLng,
            formatted: formatLocationValue(label, safeLat, safeLng)
        };

        if (localState.marker) {
            localState.marker.setLatLng([safeLat, safeLng]);
        }

        if (localState.map && centerMap) {
            localState.map.setView([safeLat, safeLng], 15);
        }

        renderSelection();
    }

    async function runSearch() {
        const localState = getState();
        if (!localState.searchInput) {
            return;
        }

        const query = localState.searchInput.value.trim();
        if (query === '') {
            renderResults([]);
            return;
        }

        if (localState.searchButton) {
            localState.searchButton.disabled = true;
        }

        try {
            const places = await searchPlaces(query);
            renderResults(places);
            if (places.length > 0) {
                const first = places[0];
                const lat = Number(first.lat);
                const lng = Number(first.lon);
                if (localState.map && Number.isFinite(lat) && Number.isFinite(lng)) {
                    localState.map.setView([lat, lng], 14);
                }
            }
        } catch (error) {
            renderResults([]);
            if (localState.resultsRoot) {
                localState.resultsRoot.innerHTML = '<div class="small text-danger">Unable to search map locations right now.</div>';
            }
        } finally {
            if (localState.searchButton) {
                localState.searchButton.disabled = false;
            }
        }
    }

    async function ensureMapInstance() {
        const localState = getState();
        if (localState.map || localState.mapInitAttempted) {
            return localState;
        }

        localState.mapInitAttempted = true;

        try {
            const L = await ensureLeaflet();
            localState.map = L.map('tbInterviewMapPickerMap').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(localState.map);

            localState.marker = L.marker(DEFAULT_CENTER).addTo(localState.map);

            localState.map.on('click', async function (event) {
                const lat = event.latlng.lat;
                const lng = event.latlng.lng;
                selectLocation('Pinned location', lat, lng, false);

                try {
                    const label = await reverseLookup(lat, lng);
                    selectLocation(label, lat, lng, false);
                } catch (error) {
                    // Keep fallback pinned label when reverse lookup fails.
                }
            });

            return localState;
        } catch (error) {
            localState.mapInitFailed = true;
            if (localState.mapRoot) {
                localState.mapRoot.innerHTML = ''
                    + '<div class="d-flex align-items-center justify-content-center h-100 px-3 py-4 text-center text-secondary" style="min-height:360px;">'
                    + '<div>'
                    + '<div class="mb-2"><i class="ti ti-map-off" style="font-size:1.8rem;"></i></div>'
                    + '<div>Map preview is unavailable in this browser/network.</div>'
                    + '<div class="small mt-1">You can still search places and select one to fill the location field.</div>'
                    + '</div>'
                    + '</div>';
            }

            return localState;
        }
    }

    function applySelectionToInput() {
        const localState = getState();
        if (!localState.activeInput || !localState.selected) {
            return;
        }

        localState.activeInput.value = localState.selected.formatted;
        localState.activeInput.dispatchEvent(new Event('input', { bubbles: true }));
        localState.activeInput.dispatchEvent(new Event('change', { bubbles: true }));

        if (localState.bootstrapModal) {
            localState.bootstrapModal.hide();
        }
    }

    async function open(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const localState = await ensureMapInstance();
        localState.activeInput = input;
        localState.currentResults = [];
        renderResults([]);

        const existing = input.value.trim();
        const existingCoordinates = parseCoordinatesFromLocation(existing);
        if (existingCoordinates) {
            const label = extractLabelFromLocation(existing) || 'Pinned location';
            selectLocation(label, existingCoordinates.lat, existingCoordinates.lng, true);
        } else {
            localState.selected = null;
            renderSelection();
            if (localState.map) {
                localState.map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            }
            if (localState.marker) {
                localState.marker.setLatLng(DEFAULT_CENTER);
            }
        }

        if (localState.searchInput) {
            localState.searchInput.value = '';
            localState.searchInput.focus();
        }

        if (localState.bootstrapModal) {
            localState.bootstrapModal.show();
        } else {
            localState.modalEl.classList.add('show');
            localState.modalEl.style.display = 'block';
            localState.modalEl.removeAttribute('aria-hidden');
        }
    }

    return {
        attach: function (input, triggerButton) {
            if (!(input instanceof HTMLInputElement) || !(triggerButton instanceof HTMLElement)) {
                return;
            }

            if (triggerButton.dataset.tbMapPickerBound === '1') {
                return;
            }
            triggerButton.dataset.tbMapPickerBound = '1';

            triggerButton.addEventListener('click', function (event) {
                event.preventDefault();
                open(input).catch(function (error) {
                    console.error('Unable to open interview map picker.', error);
                });
            });
        },
        open: open
    };
}
