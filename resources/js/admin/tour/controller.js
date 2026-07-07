// Single-area tour controller (session 362 — supersedes the session-338
// multi-page walkthrough engine).
//
// A tour is an ordered list of steps that all live within one admin area. Most
// steps are same-page: driver.js drives them directly. A step marked
// `interactive` spotlights a real link and the user's own click carries the
// tour into the next page — a one-shot launch flag (sessionStorage, consumed on
// read) tells the destination page to continue from the following step. That
// flag is the entire cross-page surface: there is no localStorage resume, no
// page-group driving, no URL-keyed page map. An abandoned tour simply ends.
//
// The reusable primitives — `resolveAnchor` / `waitForStableRect` (anchors.js)
// plus `driver().highlight()` for a buttonless single-element spotlight — stay
// independent of any tour script; a later contextual-help mode points them at
// one feature on demand.

import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { resolveAnchor, waitForStableRect } from './anchors.js';

const LAUNCH_KEY = 'np-tour-launch';

let TOURS = {};
let activeDriver = null;

export function registerTours(tours) {
    TOURS = tours || {};
}

function urls() {
    return (window.__npTour && window.__npTour.urls) || {};
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
    teardown();
}

// One-shot cross-page handoff. Set before a navigation that should continue a
// tour on the destination page; consumed (and cleared) on the next page load,
// so it can never re-trigger later.
export function setLaunchFlag(tourId, index) {
    try {
        sessionStorage.setItem(LAUNCH_KEY, JSON.stringify({ tour: tourId, index }));
    } catch {
        // sessionStorage unavailable — the current segment still runs; the
        // cross-page continuation is simply lost.
    }
}

export function consumeLaunchFlag() {
    try {
        const raw = sessionStorage.getItem(LAUNCH_KEY);
        sessionStorage.removeItem(LAUNCH_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

// Navigate to a tour's home page and start it there (the conclusion-modal
// links, and any launcher that lives outside the tour's own area).
export function gotoTour(tourId) {
    const tour = TOURS[tourId];
    if (!tour) return;
    const url = urls()[tour.startUrl];
    if (!url) return;
    setLaunchFlag(tourId, 0);
    teardown();
    window.location.assign(url);
}

export async function startTour(tourId, startIndex = 0) {
    const tour = TOURS[tourId];
    if (!tour || !Array.isArray(tour.steps) || !tour.steps.length) return;
    if (startIndex < 0 || startIndex >= tour.steps.length) return;

    const steps = tour.steps;

    // The drivable segment: from the entry step to the tour's end, or to the
    // first interactive step (inclusive) — the user's click on that element
    // navigates, and the destination page resumes the remainder via the flag.
    let segEnd = steps.length - 1;
    for (let i = startIndex; i < steps.length; i++) {
        if (steps[i].interactive) {
            segEnd = i;
            break;
        }
    }

    const driverSteps = [];
    let entryEl = null;
    for (let i = startIndex; i <= segEnd; i++) {
        const el = await resolveAnchor(steps[i].anchor);
        if (i === startIndex) entryEl = el;
        driverSteps.push(buildStep(steps[i], el, i, steps.length, i === startIndex));
    }

    // Wait for the entry step's target to settle its geometry before driver
    // positions/scrolls the spotlight — on a fresh page load Livewire forms are
    // still hydrating and growing, and framing against stale bounds lands the
    // spotlight off the fold (the session-361 lesson). The window is long and
    // resets on any change so an early hydration plateau can't be mistaken for
    // the final layout.
    await waitForStableRect(entryEl, { interval: 80, stableReads: 6, timeout: 6000 });

    teardown();

    activeDriver = driver({
        allowClose: true,
        overlayColor: '#0b1220',
        overlayOpacity: 0.55,
        smoothScroll: true,
        stagePadding: 6,
        stageRadius: 8,
        popoverClass: 'np-tour-popover',
        steps: driverSteps,
        onHighlightStarted: (element) => {
            // Clear any stale spotlight class driver left on a previous element
            // when a Livewire morph detached its reference — otherwise two
            // elements stay ringed.
            document.querySelectorAll('.driver-active-element').forEach((el) => {
                if (el !== element && el.id !== 'driver-dummy-element') {
                    el.classList.remove('driver-active-element');
                }
            });

            // Interactive steps: a one-shot listener persists the next step,
            // then the element's own navigation carries the user there. Attached
            // only while the step is active, so a click on the element outside
            // its step can never arm a stray continuation.
            const local = activeDriver ? activeDriver.getActiveIndex() : 0;
            const globalIndex = startIndex + local;
            const step = steps[globalIndex];
            if (step && step.interactive && element && globalIndex < steps.length - 1) {
                element.addEventListener(
                    'click',
                    () => setLaunchFlag(tourId, globalIndex + 1),
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
            if (!activeDriver) return;
            if (activeDriver.getActiveIndex() < driverSteps.length - 1) {
                activeDriver.moveNext();
            } else {
                // Next only shows on a segment's last step when the tour ends
                // here or the interactive element failed to resolve (nothing to
                // click into) — either way the tour ends cleanly.
                stopTour();
            }
        },
        onPrevClick: () => {
            if (!activeDriver) return;
            if (activeDriver.getActiveIndex() > 0) activeDriver.movePrevious();
        },
        onCloseClick: () => stopTour(),
        onDestroyStarted: () => stopTour(),
    });

    activeDriver.drive(0);
}

function buildStep(step, element, globalIndex, total, isSegmentStart) {
    const isLast = globalIndex === total - 1;
    const progress = `<span class="np-tour-progress">Step ${globalIndex + 1} of ${total}</span>`;

    // Interactive steps hide Next — the user advances by clicking the real
    // element the step points at. But only when that element actually resolved;
    // if it didn't (e.g. no record to open into), keep Next so the tour can't
    // strand the user on a centered step with no way forward.
    const buttons =
        step.interactive && element ? ['previous', 'close'] : ['previous', 'next', 'close'];

    const popover = {
        title: step.title || '',
        description: progress + (step.description || ''),
        side: step.side || 'bottom',
        align: step.align || 'start',
        showButtons: buttons,
        // Back never crosses a page boundary — the segment's first step is the
        // floor (for the tour's true first step, that is also step 1 of N).
        disableButtons: isSegmentStart ? ['previous'] : [],
        nextBtnText: isLast ? 'Done' : 'Next →',
        prevBtnText: '← Back',
    };
    const built = { popover };
    // For self-target `data-tour` anchors, hand driver a live CSS selector so it
    // re-queries a fresh element on every highlight — immune to the Livewire DOM
    // morph that detaches a captured element between two steps on the same page.
    // Traversal/nav anchors keep the captured element.
    if (step.anchor && step.anchor.tour && !step.anchor.target) {
        built.element = `[data-tour="${step.anchor.tour}"]`;
    } else if (element) {
        built.element = element;
    }
    return built;
}
