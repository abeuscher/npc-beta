// Heroicon picker popover for the Quill toolbar. Loaded by quill-editor.js.
//
// Lazy-fetches the outline-set icon manifest from /admin/heroicons on first
// open, caches it in module scope, and renders a search + grid popover.
// Clicking an icon calls the supplied insert callback with `{ name, svg }`
// so the caller can build the appropriate Quill embed.

let iconsPromise = null;
let popoverEl = null;
let outsideClickHandler = null;
let escapeKeyHandler = null;
let manifestUrl = '/admin/heroicons';

export function setHeroiconsUrl(url) {
    if (typeof url === 'string' && url !== '' && url !== manifestUrl) {
        manifestUrl = url;
        iconsPromise = null;
    }
}

function fetchIcons() {
    if (iconsPromise) {
        return iconsPromise;
    }
    iconsPromise = fetch(manifestUrl, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) {
                throw new Error('Failed to load heroicons (' + res.status + ')');
            }
            return res.json();
        })
        .then((data) => Array.isArray(data.icons) ? data.icons : [])
        .catch((err) => {
            iconsPromise = null;
            throw err;
        });
    return iconsPromise;
}

function closePopover() {
    if (popoverEl) {
        popoverEl.remove();
        popoverEl = null;
    }
    if (outsideClickHandler) {
        document.removeEventListener('mousedown', outsideClickHandler);
        outsideClickHandler = null;
    }
    if (escapeKeyHandler) {
        document.removeEventListener('keydown', escapeKeyHandler);
        escapeKeyHandler = null;
    }
}

function buildPopover(anchorEl, onPick) {
    closePopover();

    popoverEl = document.createElement('div');
    popoverEl.className = 'heroicon-picker';

    const rect = anchorEl.getBoundingClientRect();
    popoverEl.style.position = 'fixed';
    popoverEl.style.top = rect.bottom + 4 + 'px';
    popoverEl.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 360)) + 'px';

    const search = document.createElement('input');
    search.type = 'text';
    search.placeholder = 'Search icons…';
    search.className = 'heroicon-picker__search';
    search.autocomplete = 'off';
    popoverEl.appendChild(search);

    const grid = document.createElement('div');
    grid.className = 'heroicon-picker__grid';
    grid.setAttribute('role', 'listbox');
    popoverEl.appendChild(grid);

    const status = document.createElement('div');
    status.className = 'heroicon-picker__status';
    status.textContent = 'Loading…';
    popoverEl.appendChild(status);

    document.body.appendChild(popoverEl);

    fetchIcons()
        .then((icons) => {
            status.remove();
            const render = (filter) => {
                grid.innerHTML = '';
                const lower = filter.trim().toLowerCase();
                const matches = lower === ''
                    ? icons
                    : icons.filter((icon) => icon.name.toLowerCase().includes(lower));

                if (matches.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'heroicon-picker__status';
                    empty.textContent = 'No matches.';
                    grid.appendChild(empty);
                    return;
                }

                matches.slice(0, 240).forEach((icon) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'heroicon-picker__icon';
                    btn.title = icon.name;
                    btn.setAttribute('aria-label', icon.name);
                    btn.innerHTML = icon.svg;
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        onPick({ name: icon.name, svg: icon.svg });
                        closePopover();
                    });
                    grid.appendChild(btn);
                });
            };

            render('');
            search.addEventListener('input', () => render(search.value));
            search.focus();
        })
        .catch((err) => {
            status.textContent = 'Failed to load icons.';
            console.error('Heroicon picker', err);
        });

    outsideClickHandler = (e) => {
        if (popoverEl && !popoverEl.contains(e.target) && e.target !== anchorEl) {
            closePopover();
        }
    };
    escapeKeyHandler = (e) => {
        if (e.key === 'Escape') {
            closePopover();
        }
    };
    document.addEventListener('mousedown', outsideClickHandler);
    document.addEventListener('keydown', escapeKeyHandler);
}

export function openHeroiconPicker(anchorEl, onPick) {
    if (popoverEl) {
        closePopover();
        return;
    }
    buildPopover(anchorEl, onPick);
}
