import { registerTour, resumeIfActive, startTour } from './controller.js';
import { productTour } from './tours/product-tour.js';

// Wire the product tour into the admin panel: register the script, expose a
// delegated launcher, and resume an in-progress tour on every page load.
export function initTour() {
    registerTour(productTour);

    // Any element carrying [data-np-tour-start] launches the tour. Delegated on
    // the document so it survives Livewire DOM morphs (the dashboard widget can
    // re-render without re-binding).
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-np-tour-start]');
        if (!trigger) return;
        event.preventDefault();
        startTour();
    });

    const resume = () => resumeIfActive();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resume);
    } else {
        resume();
    }
    // Resume after a Filament/Livewire SPA navigation too, in case the panel
    // ever enables wire:navigate (the tour's own transitions are hard loads).
    document.addEventListener('livewire:navigated', resume);
}
