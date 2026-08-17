// Widget Alpine components → Alpine.data() registration (session 375).
//
// Widgets that need JavaScript define an Alpine factory in their own
// `app/Widgets/{Name}/script.js`, which the build server concatenates into the
// public widget bundle and which assigns onto `window.NPWidgets`. Templates then
// reference the component *by name* — `x-data="barChart"`.
//
// Referencing it by name rather than calling `NPWidgets.barChart()` inline is
// what the enforced public CSP requires: the CSP-safe Alpine build resolves
// expressions against the component scope only, so a bare `window` global is an
// "Undefined variable". Registration closes that gap without moving widget JS
// out of the widget folder.
//
// Both surfaces call this: the public site (its own Alpine, the CSP build) and
// the admin panel (Filament's Alpine, which renders the same widget templates in
// the page-builder preview). The widget bundle already loads on both, so the
// only thing that differed was the registration — hence one shared routine.
//
// The registry is read inside the `alpine:init` handler, not at import time, so
// bundle/script ordering does not matter: every widget script has run by the
// time Alpine initializes.
export function registerWidgetComponents() {
    document.addEventListener('alpine:init', () => {
        const registry = window.NPWidgets || {}

        for (const [name, factory] of Object.entries(registry)) {
            if (typeof factory === 'function') {
                window.Alpine.data(name, factory)
            }
        }
    })
}
