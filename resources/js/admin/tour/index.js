import { consumeLaunchFlag, gotoTour, registerTours, startTour } from './controller.js';
import { dashboardTour } from './tours/dashboard.js';
import { crmTour } from './tours/crm.js';
import { cmsTour } from './tours/cms.js';

const TOURS = {
    dashboard: dashboardTour,
    crm: crmTour,
    cms: cmsTour,
};

// Wire the three single-area tours into the admin panel: register the scripts,
// expose the delegated launchers, and continue a handed-off tour on page load.
export function initTour() {
    registerTours(TOURS);

    // Delegated on the document so launchers survive Livewire DOM morphs.
    // [data-np-tour-start="<id>"] starts that tour on the current page;
    // [data-np-tour-goto="<id>"] navigates to the tour's home page and starts
    // it there (the dashboard tour's conclusion links).
    document.addEventListener('click', (event) => {
        const start = event.target.closest('[data-np-tour-start]');
        if (start) {
            event.preventDefault();
            startTour(start.getAttribute('data-np-tour-start') || 'dashboard');
            return;
        }
        const goto = event.target.closest('[data-np-tour-goto]');
        if (goto) {
            event.preventDefault();
            gotoTour(goto.getAttribute('data-np-tour-goto'));
        }
    });

    // A one-shot launch flag set before a navigation (a goto link, or an
    // interactive step's click-through) continues the tour here. Wait for the
    // full `load` event, not DOMContentLoaded: anchor resolution opens collapsed
    // sidebar groups by clicking their Alpine toggles, and at DOMContentLoaded
    // Alpine hasn't booted yet — the click lands on a dead button and the
    // group's items stay hidden under the spotlight.
    const resume = () => {
        const flag = consumeLaunchFlag();
        if (flag && TOURS[flag.tour]) startTour(flag.tour, flag.index || 0);
    };
    if (document.readyState === 'complete') {
        resume();
    } else {
        window.addEventListener('load', resume, { once: true });
    }
    // And after a Filament/Livewire SPA navigation too, in case the panel ever
    // enables wire:navigate (the tours' own transitions are hard loads).
    document.addEventListener('livewire:navigated', resume);
}
