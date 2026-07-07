// Anchor resolution. Every selector we depend on is one we own — a `data-tour`
// attribute we inject via render hooks, or a sidebar link matched by our own
// route URL — never a Filament-generated class or DOM shape that can churn
// across Filament upgrades (the session 249 selector-fragility mitigation).

export function waitForElement(getter, { timeout = 8000, interval = 80 } = {}) {
    return new Promise((resolve) => {
        const start = Date.now();
        const tick = () => {
            let el = null;
            try {
                el = getter();
            } catch {
                el = null;
            }
            if (el) return resolve(el);
            if (Date.now() - start >= timeout) return resolve(null);
            setTimeout(tick, interval);
        };
        tick();
    });
}

// Resolve once an element's geometry has settled — its bounding box unchanged
// for `stableReads` consecutive polls. `waitForElement` resolves the moment a
// marker *exists*, but existence is not settled layout: on a record page the
// Livewire form above a panel keeps hydrating and growing after the marker
// appears, so the panel shifts downward. If driver.js scrolls/positions the
// spotlight against that pre-settle rect, the highlight lands off-target and
// the target can end up below the fold (the session-338 membership step). Gate
// the drive on this so every step is framed against final geometry, not stale.
export function waitForStableRect(el, { interval = 60, stableReads = 4, timeout = 3000 } = {}) {
    return new Promise((resolve) => {
        if (!el || typeof el.getBoundingClientRect !== 'function') {
            return resolve(el);
        }
        const start = Date.now();
        let last = null;
        let stable = 0;
        const tick = () => {
            const r = el.getBoundingClientRect();
            const key = `${Math.round(r.top)}|${Math.round(r.left)}|${Math.round(r.width)}|${Math.round(r.height)}`;
            if (key === last) {
                stable += 1;
            } else {
                stable = 0;
                last = key;
            }
            if (stable >= stableReads) return resolve(el);
            if (Date.now() - start >= timeout) return resolve(el);
            setTimeout(tick, interval);
        };
        tick();
    });
}

function isHidden(el) {
    if (!el) return true;
    if (el.offsetParent === null) return true;
    const r = el.getBoundingClientRect();
    return r.width === 0 && r.height === 0;
}

// Collapsed sidebar groups (every group is `->collapsed()` in this panel) hide
// their links until the group is opened. Open the parent group so the link is
// a real, measurable target before driver.js positions the popover.
async function ensureNavVisible(link) {
    if (!isHidden(link)) return;
    const group = link.closest('.fi-sidebar-group');
    const toggle = group && group.querySelector('.fi-sidebar-group-button, button');
    if (toggle) {
        toggle.click();
        await waitForElement(() => (isHidden(link) ? null : link), {
            timeout: 1500,
            interval: 60,
        });
    }
}

// anchor descriptors:
//   null                                   → centered, anchorless step
//   { nav: 'contacts' }                    → sidebar link, matched by our route URL
//   { navGroup: 'contacts' }               → the sidebar group containing that link
//   { tour: 'resource.records' }           → the [data-tour] marker element itself
//   { tour: 'resource.records', target: 'next' }   → the marker's next sibling
//   { tour: 'record.custom-fields', target: 'parent' } → the marker's parent element
//
// Any descriptor may carry `timeout` (ms) to shorten the wait for anchors that
// legitimately may not exist on a given install (e.g. a form section hidden
// because nothing is configured) — the step then falls back to a centered
// popover quickly instead of stalling the whole segment on the default wait.
export async function resolveAnchor(anchor) {
    if (!anchor) return null;

    const wait = anchor.timeout ? { timeout: anchor.timeout } : {};

    if (anchor.nav) {
        const urls = (window.__npTour && window.__npTour.urls) || {};
        const url = urls[anchor.nav];
        if (!url) return null;
        const path = new URL(url, window.location.origin).pathname;
        const link = await waitForElement(
            () => document.querySelector(`.fi-sidebar a[href$="${path}"]`),
            wait
        );
        if (link) await ensureNavVisible(link);
        return link;
    }

    // The whole sidebar group ("menu heading") that contains a given nav link —
    // located through our own route URL, then the same `.fi-sidebar-group`
    // containment hop ensureNavVisible already takes.
    if (anchor.navGroup) {
        const urls = (window.__npTour && window.__npTour.urls) || {};
        const url = urls[anchor.navGroup];
        if (!url) return null;
        const path = new URL(url, window.location.origin).pathname;
        const link = await waitForElement(
            () => document.querySelector(`.fi-sidebar a[href$="${path}"]`),
            wait
        );
        if (!link) return null;
        return link.closest('.fi-sidebar-group') || link;
    }

    // The table row whose record link points at a given URL-map page — used to
    // spotlight a specific (seeded) record in a list and invite the click that
    // navigates into it. Matched by our own route URL, never a row index.
    if (anchor.navRow) {
        const urls = (window.__npTour && window.__npTour.urls) || {};
        const url = urls[anchor.navRow];
        if (!url) return null;
        const path = new URL(url, window.location.origin).pathname;
        return await waitForElement(() => {
            const marker = document.querySelector('[data-tour="resource.records"]');
            const scope = (marker && marker.nextElementSibling) || document;
            const link = scope.querySelector(`a[href$="${path}"]`);
            return link ? link.closest('tr') : null;
        });
    }

    if (anchor.tour) {
        const marker = await waitForElement(() =>
            document.querySelector(`[data-tour="${anchor.tour}"]`)
        );
        if (!marker) return null;
        if (anchor.target === 'next') return marker.nextElementSibling || marker;
        if (anchor.target === 'parent') return marker.parentElement || marker;
        return marker;
    }

    return null;
}
