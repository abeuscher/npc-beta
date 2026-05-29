window.NPWidgets = window.NPWidgets || {};

// Server-rendered mini-calendar. All date math lives in the Blade template
// (Carbon); this script only toggles visibility. Two list modes, both with
// their content already rendered server-side:
//   day   — clicking a day shows that day's event list; today (or the soonest
//           upcoming day) is selected on load. Month-nav CLEARS the list — the
//           selected day belongs to a month, so paging away deselects it.
//   month — the list below shows the whole visible month and follows month-nav
//           (an empty month shows its own "no events" line).
// The only "date" work here is lexicographic string comparison of YYYY-MM-DD
// keys (chronological, no arithmetic) to pick the load-time day.
window.NPWidgets.eventMiniCalendar = function () {
    return {
        init() {
            const root = this.$el;
            const mode = root.dataset.listMode || 'day';
            const panels = Array.from(root.querySelectorAll('.emc-month'));
            const monthLists = Array.from(root.querySelectorAll('[data-month-events]'));
            if (!panels.length) return;

            let current = parseInt(root.dataset.currentIndex || '1', 10);
            if (Number.isNaN(current) || !panels[current]) current = 0;

            const dayContainer = root.querySelector('.emc-events--day');
            const dayBlocks = Array.from(root.querySelectorAll('[data-day-events]'));
            const empty = root.querySelector('.emc-events--day .emc-events__empty');

            // Day mode: blank the area beneath the calendar and drop the
            // selection. Called on every month-nav so the list never persists a
            // day from a month you've paged away from.
            const clearDay = () => {
                dayBlocks.forEach((block) => block.setAttribute('hidden', ''));
                root.querySelectorAll('.emc-day--selected').forEach((cell) => cell.classList.remove('emc-day--selected'));
                if (empty) empty.setAttribute('hidden', '');
                if (dayContainer) dayContainer.setAttribute('hidden', '');
            };

            const selectDay = (key) => {
                let matched = false;
                dayBlocks.forEach((block) => {
                    const isMatch = block.dataset.dayEvents === key;
                    block.toggleAttribute('hidden', !isMatch);
                    if (isMatch) matched = true;
                });
                root.querySelectorAll('.emc-day[data-day]').forEach((cell) => {
                    cell.classList.toggle('emc-day--selected', cell.dataset.day === key);
                });
                if (dayContainer) dayContainer.removeAttribute('hidden');
                if (empty) empty.toggleAttribute('hidden', matched);
            };

            // Show month `index`: flips the grid panel and (month mode) the
            // matching event list; (day mode) clears the day display.
            const showMonth = (index) => {
                if (index < 0 || index >= panels.length) return;
                panels.forEach((panel, i) => panel.toggleAttribute('hidden', i !== index));
                monthLists.forEach((list) => {
                    const i = parseInt(list.dataset.monthEvents, 10);
                    list.toggleAttribute('hidden', i !== index);
                });
                current = index;
                if (mode === 'day') clearDay();
            };

            panels.forEach((panel, index) => {
                const prev = panel.querySelector('.emc-month__arrow--prev');
                const next = panel.querySelector('.emc-month__arrow--next');
                if (prev) prev.addEventListener('click', () => showMonth(index - 1));
                if (next) next.addEventListener('click', () => showMonth(index + 1));
            });

            // Expand/collapse an event's inline details (both list modes). No-LP
            // events live entirely here; LP events carry an outbound link inside.
            root.querySelectorAll('.emc-event__toggle').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const detail = toggle.nextElementSibling;
                    const open = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', String(!open));
                    toggle.classList.toggle('emc-event--open', !open);
                    if (detail) detail.toggleAttribute('hidden', open);
                });
            });

            if (mode !== 'day') return;

            root.querySelectorAll('.emc-day[data-day]').forEach((cell) => {
                cell.addEventListener('click', () => selectDay(cell.dataset.day));
            });

            // Load: today if it has events, else the soonest upcoming day, else
            // the empty line. Blocks are rendered chronologically, so the first
            // key >= today is the soonest upcoming (string compare = date order).
            const today = root.dataset.today || '';
            const keys = dayBlocks.map((b) => b.dataset.dayEvents);
            const initial = keys.includes(today) ? today : keys.find((k) => k >= today);
            if (initial) {
                selectDay(initial);
            } else {
                if (dayContainer) dayContainer.removeAttribute('hidden');
                if (empty) empty.removeAttribute('hidden');
            }
        },
    };
};
