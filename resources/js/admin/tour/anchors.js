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
//   { tour: 'resource.records' }           → the [data-tour] marker element itself
//   { tour: 'resource.records', target: 'next' }   → the marker's next sibling
//   { tour: 'page.content', target: 'parent' }     → the marker's parent element
export async function resolveAnchor(anchor) {
    if (!anchor) return null;

    if (anchor.nav) {
        const urls = (window.__npTour && window.__npTour.urls) || {};
        const url = urls[anchor.nav];
        if (!url) return null;
        const path = new URL(url, window.location.origin).pathname;
        const link = await waitForElement(() =>
            document.querySelector(`.fi-sidebar a[href$="${path}"]`)
        );
        if (link) await ensureNavVisible(link);
        return link;
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
