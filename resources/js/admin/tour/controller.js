// Multi-page tour controller.
//
// A tour is a flat, ordered list of steps; each step names the admin page it
// lives on (a key into the server-injected URL map) plus its anchor + copy.
// driver.js is single-page, so we drive one *page-group* (the maximal run of
// consecutive steps that share the current page) per driver instance, and own
// every cross-page transition: the active step index is persisted before a hard
// navigation and resumed on the next page load.
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
        overlayOpacity: 0.5,
        smoothScroll: true,
        stagePadding: 6,
        stageRadius: 8,
        steps: driverSteps,
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
    const popover = {
        title: step.title || '',
        description: progress + (step.description || ''),
        side: step.side || 'bottom',
        align: step.align || 'start',
        showButtons: ['previous', 'next', 'close'],
        disableButtons: isFirst ? ['previous'] : [],
        nextBtnText: isLast ? 'Done' : 'Next →',
        prevBtnText: '← Back',
    };
    const built = { popover };
    if (element) built.element = element;
    return built;
}
