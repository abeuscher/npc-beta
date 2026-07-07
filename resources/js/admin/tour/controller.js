// Multi-page tour controller.
//
// A tour is a flat, ordered list of steps; each step names the admin page it
// lives on (a key into the server-injected URL map) plus its anchor + copy.
// driver.js is single-page, so we drive one *page-group* (the maximal run of
// consecutive steps that share the current page) per driver instance, and own
// every cross-page transition: the active step index is persisted before a hard
// navigation and resumed on the next page load.
//
// Interactive steps (step.interactive) hand control to the user: the spotlighted
// element is the real link/button, the Next button is hidden, and clicking the
// element advances the tour through the navigation it triggers (a one-shot
// listener persists the next step, then the native click navigates and resume
// picks it up on the destination page).
//
// The reusable primitives here — `resolveAnchor` (anchors.js) plus
// `driver().highlight()` for a buttonless single-element spotlight — are what a
// later contextual-help mode points at one feature on demand; keep them
// independent of the tour script.

import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { getState, setState, clearState } from './state.js';
import { resolveAnchor, waitForStableRect } from './anchors.js';

let activeDriver = null;
let TOUR = [];
// The persistent control bar element refs, and the current page-group bounds the
// advance/back paths and the bar both read (single source of truth — the popover
// buttons and the bar drive the exact same transitions, no divergent state).
let tourBar = null;
let groupCtx = null;

export function registerTour(steps) {
    TOUR = Array.isArray(steps) ? steps : [];
}

function urls() {
    return (window.__npTour && window.__npTour.urls) || {};
}

function norm(path) {
    if (!path) return null;
    const p = String(path).replace(/\/+$/, '');
    return p === '' ? '/' : p;
}

// A step's page key maps to a route URL the server only emits when the viewer's
// role can actually reach that page. No URL ⇒ the step is inaccessible.
function pathForPage(pageKey) {
    const url = urls()[pageKey];
    if (!url) return null;
    return norm(new URL(url, window.location.origin).pathname);
}

// The tour as the current viewer can walk it: steps whose page the role cannot
// reach drop out, so a restricted role gets a shorter tour rather than a 404.
function effectiveTour() {
    return TOUR.filter((step) => pathForPage(step.page) !== null);
}

function currentPath() {
    return norm(window.location.pathname);
}

function teardown() {
    unmountTourBar();
    groupCtx = null;
    if (activeDriver) {
        const d = activeDriver;
        activeDriver = null;
        try {
            d.destroy();
        } catch {
            // already torn down
        }
    }
}

// Advance / retreat: the single transition path the popover buttons AND the
// persistent bar both call, so the two control sets can never diverge. Within
// the current page-group they move the driver; at a group edge they persist the
// next index and hard-navigate; off the last step they end the tour.
function advanceTour() {
    if (!activeDriver || !groupCtx) return;
    const local = activeDriver.getActiveIndex();
    if (local < groupCtx.len - 1) {
        setState({ active: true, index: groupCtx.start + local + 1 });
        activeDriver.moveNext();
    } else if (!groupCtx.isLast) {
        const tour = effectiveTour();
        setState({ active: true, index: groupCtx.end + 1 });
        navigateTo(tour[groupCtx.end + 1].page);
    } else {
        stopTour();
    }
}

function retreatTour() {
    if (!activeDriver || !groupCtx) return;
    const local = activeDriver.getActiveIndex();
    if (local > 0) {
        setState({ active: true, index: groupCtx.start + local - 1 });
        activeDriver.movePrevious();
    } else if (!groupCtx.isFirst) {
        const tour = effectiveTour();
        setState({ active: true, index: groupCtx.start - 1 });
        navigateTo(tour[groupCtx.start - 1].page);
    }
}

// A second, always-visible set of tour controls (session 361). A mispositioned
// popover can never strand the user: Back / Next / Exit and a "Step X of Y"
// indicator are pinned to the bottom of the viewport regardless of where the
// popover lands. Wired to the same advance/back/stop paths as the popover, so
// there is no separate state to keep in sync.
function mountTourBar() {
    if (tourBar) return tourBar;

    const bar = document.createElement('div');
    bar.className = 'np-tour-bar';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Tour controls');
    // Keep clicks on the bar from reaching driver's overlay (which closes the
    // tour on click); the button handlers have already run by the time this
    // bubbles up here.
    bar.addEventListener('click', (event) => event.stopPropagation());

    const exit = document.createElement('button');
    exit.type = 'button';
    exit.className = 'np-tour-bar__exit';
    exit.textContent = 'Exit';
    exit.addEventListener('click', () => stopTour());

    const back = document.createElement('button');
    back.type = 'button';
    back.className = 'np-tour-bar__back';
    back.textContent = '← Back';
    back.addEventListener('click', () => retreatTour());

    const progress = document.createElement('span');
    progress.className = 'np-tour-bar__progress';

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'np-tour-bar__next';
    next.textContent = 'Next →';
    next.addEventListener('click', () => advanceTour());

    bar.append(exit, back, progress, next);
    document.body.appendChild(bar);

    tourBar = { bar, back, progress, next };
    return tourBar;
}

function updateTourBar() {
    if (!tourBar || !activeDriver || !groupCtx) return;
    const globalIndex = groupCtx.start + activeDriver.getActiveIndex();
    const total = groupCtx.total;
    tourBar.progress.textContent = `Step ${globalIndex + 1} of ${total}`;
    tourBar.back.disabled = globalIndex === 0;
    tourBar.next.textContent = globalIndex === total - 1 ? 'Done' : 'Next →';
}

function unmountTourBar() {
    if (tourBar) {
        tourBar.bar.remove();
        tourBar = null;
    }
}

export function stopTour() {
    clearState();
    teardown();
}

export function startTour() {
    if (!effectiveTour().length) return;
    setState({ active: true, index: 0 });
    runFromCurrentPage();
}

export function resumeIfActive() {
    const state = getState();
    if (state && state.active) runFromCurrentPage();
}

function navigateTo(pageKey) {
    const path = pathForPage(pageKey);
    if (!path) {
        stopTour();
        return;
    }
    teardown();
    window.location.assign(path);
}

async function runFromCurrentPage() {
    const state = getState();
    if (!state || !state.active) return;

    const tour = effectiveTour();
    const index = state.index || 0;
    if (index < 0 || index >= tour.length) {
        clearState();
        return;
    }

    const step = tour[index];
    const stepPath = pathForPage(step.page);

    // On the wrong page for this step → persist and hard-navigate; resume
    // re-fires on the next page load.
    if (stepPath && currentPath() !== stepPath) {
        setState({ active: true, index });
        navigateTo(step.page);
        return;
    }

    // The maximal run of contiguous steps that live on the current page.
    const onThisPage = (i) => pathForPage(tour[i].page) === currentPath();
    let groupStart = index;
    let groupEnd = index;
    while (groupStart > 0 && onThisPage(groupStart - 1)) groupStart--;
    while (groupEnd < tour.length - 1 && onThisPage(groupEnd + 1)) groupEnd++;

    const driverSteps = [];
    let activeEl = null;
    for (let i = groupStart; i <= groupEnd; i++) {
        const el = await resolveAnchor(tour[i].anchor);
        if (i === index) activeEl = el;
        driverSteps.push(buildStep(tour[i], el, i, tour.length));
    }

    // Wait for the entry step's target to settle its geometry before driver
    // positions/scrolls the spotlight. On a fresh page load the record form is
    // still hydrating and growing, so an anchored step (e.g. membership) framed
    // immediately lands against stale bounds and scrolls off the fold. Settling
    // first lets driver's own scroll-into-view + popover placement land against
    // final geometry — generalised to every page-group entry, not just one step.
    // The window is deliberately long (and resets on any change) so an early
    // hydration plateau can't be mistaken for the final layout before a later
    // shift lands.
    await waitForStableRect(activeEl, { interval: 80, stableReads: 6, timeout: 6000 });

    teardown();

    const isFirstGroup = groupStart === 0;
    const isLastGroup = groupEnd === tour.length - 1;

    groupCtx = {
        start: groupStart,
        end: groupEnd,
        isFirst: isFirstGroup,
        isLast: isLastGroup,
        len: driverSteps.length,
        total: tour.length,
    };

    activeDriver = driver({
        allowClose: true,
        overlayColor: '#0b1220',
        overlayOpacity: 0.55,
        smoothScroll: true,
        stagePadding: 6,
        stageRadius: 8,
        popoverClass: 'np-tour-popover',
        steps: driverSteps,
        // Interactive steps: attach a one-shot listener so the user's click on
        // the real element advances the tour through its own navigation.
        onHighlightStarted: (element) => {
            // Clear any stale spotlight class driver left on a previous element
            // when a Livewire morph detached its reference — otherwise two
            // elements stay ringed (seen on the same-page help finale).
            document.querySelectorAll('.driver-active-element').forEach((el) => {
                if (el !== element && el.id !== 'driver-dummy-element') {
                    el.classList.remove('driver-active-element');
                }
            });

            const st = getState();
            const gi = st ? st.index : null;
            const t = gi != null ? tour[gi] : null;
            if (t && t.interactive && element) {
                element.addEventListener(
                    'click',
                    () => setState({ active: true, index: gi + 1 }),
                    { once: true, capture: true }
                );
            }

            // Keep the persistent bar's progress + button states in step with the
            // popover as the user moves within the page-group.
            updateTourBar();
        },
        // A distinct "Exit Tour" affordance in the popover, same effect as the ✕.
        onPopoverRender: (popover) => {
            if (!popover.footer) return;
            const exit = document.createElement('button');
            exit.type = 'button';
            exit.className = 'np-tour-exit-btn';
            exit.textContent = 'Exit Tour';
            exit.addEventListener('click', () => stopTour());
            popover.footer.insertBefore(exit, popover.footer.firstChild);
        },
        onNextClick: () => advanceTour(),
        onPrevClick: () => retreatTour(),
        onCloseClick: () => stopTour(),
        onDestroyStarted: () => stopTour(),
    });

    activeDriver.drive(index - groupStart);

    // Mount the always-visible control bar and sync it to the entry step. It is
    // torn down with the driver (teardown) and on stop, so it never leaks past
    // the tour or across a cross-page navigation (the next page remounts it).
    mountTourBar();
    updateTourBar();
}

function buildStep(step, element, globalIndex, total) {
    const isLast = globalIndex === total - 1;
    const isFirst = globalIndex === 0;
    const progress = `<span class="np-tour-progress">Step ${globalIndex + 1} of ${total}</span>`;

    // Interactive steps hide Next — the user advances by clicking the real
    // element the step points at. But only when that element actually resolved;
    // if it didn't (e.g. no contacts to open into), keep Next so the tour can't
    // strand the user on a centered step with no way forward.
    const buttons =
        step.interactive && element ? ['previous', 'close'] : ['previous', 'next', 'close'];

    const popover = {
        title: step.title || '',
        description: progress + (step.description || ''),
        side: step.side || 'bottom',
        align: step.align || 'start',
        showButtons: buttons,
        disableButtons: isFirst ? ['previous'] : [],
        nextBtnText: isLast ? 'Done' : 'Next →',
        prevBtnText: '← Back',
    };
    const built = { popover };
    // For self-target `data-tour` anchors, hand driver a live CSS selector so it
    // re-queries a fresh element on every highlight — immune to the Livewire DOM
    // morph that detaches a captured element between two steps on the same page
    // (e.g. the membership panel and "View transactions" on a contact record).
    // Traversal anchors (next/parent/navRow) live on list pages we navigate away
    // from, so the captured element is fine.
    if (step.anchor && step.anchor.tour && !step.anchor.target) {
        built.element = `[data-tour="${step.anchor.tour}"]`;
    } else if (element) {
        built.element = element;
    }
    return built;
}
