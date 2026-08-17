window.NPWidgets = window.NPWidgets || {};

// Dropdown state for the desktop nav. Lifted out of an inline `x-data` at
// session 375: the public site runs Alpine's CSP-safe build, whose expression
// grammar has no inline method definitions, arrow functions, multi-statement
// handlers, or optional chaining — all four of which the template used.
//
// The keyboard behaviour is session-334 lineage and is reproduced exactly:
// arrow-down opens a dropdown and moves focus to its first menu item; escape
// inside a dropdown closes everything and returns focus to the item's own menu
// link; escape anywhere on the page closes everything.
//
// Handlers that need the element the listener sits on take `$event` and read
// `currentTarget` — inside a component method `this.$el` is the component root
// (the <nav>), not the element carrying the directive.
window.NPWidgets.nav = function () {
    return {
        activeDropdown: null,
        hoverTimeout: null,

        openDropdown(id) {
            clearTimeout(this.hoverTimeout);
            this.activeDropdown = id;
        },

        closeDropdown(id) {
            this.hoverTimeout = setTimeout(() => {
                if (this.activeDropdown === id) this.activeDropdown = null;
            }, 150);
        },

        closeAll() {
            this.activeDropdown = null;
        },

        openDropdownAndFocus(id, event) {
            this.openDropdown(id);

            const item = event.currentTarget.closest('.widget-nav__item');

            this.$nextTick(() => {
                const first = item ? item.querySelector('[role=menu] [role=menuitem]') : null;
                if (first) first.focus();
            });
        },

        closeAllAndRestoreFocus(event) {
            this.closeAll();

            const item = event.currentTarget.closest('.widget-nav__item');
            const target = item ? item.querySelector('[role=menuitem]') : null;
            if (target) target.focus();
        },
    };
};
