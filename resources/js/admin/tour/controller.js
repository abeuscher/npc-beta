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
import { resolveAnchor } from './anchors.js';

let activeDriver = null;
let TOUR = [];

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
    for (let i = groupStart; i <= groupEnd; i++) {
        const el = await resolveAnchor(tour[i].anchor);
        driverSteps.push(buildStep(tour[i], el, i, tour.length));
    }

    teardown();

    const isFirstGroup = groupStart === 0;
    const isLastGroup = groupEnd === tour.length - 1;

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
        onNextClick: () => {
            const local = activeDriver.getActiveIndex();
            if (local < driverSteps.length - 1) {
                setState({ active: true, index: groupStart + local + 1 });
                activeDriver.moveNext();
            } else if (!isLastGroup) {
                setState({ active: true, index: groupEnd + 1 });
                navigateTo(tour[groupEnd + 1].page);
            } else {
                stopTour();
            }
        },
        onPrevClick: () => {
            const local = activeDriver.getActiveIndex();
            if (local > 0) {
                setState({ active: true, index: groupStart + local - 1 });
                activeDriver.movePrevious();
            } else if (!isFirstGroup) {
                setState({ active: true, index: groupStart - 1 });
                navigateTo(tour[groupStart - 1].page);
            }
        },
        onCloseClick: () => stopTour(),
        onDestroyStarted: () => stopTour(),
    });

    activeDriver.drive(index - groupStart);
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
