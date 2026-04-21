// When a collapsible Filament sidebar group is expanded and its newly-revealed
// children overflow the sidebar's viewable area, scroll the sidebar so the
// group fits. If the group is taller than the sidebar, keep the header row
// visible and let the last item(s) fall off the bottom.
//
// Wired through click delegation on `.fi-sidebar-group button` because
// Filament's collapse transition runs after the click and we want to measure
// once the expanded items have their real height.

const TRANSITION_SETTLE_MS = 320;

function findScrollParent(el) {
    let node = el.parentElement;
    while (node) {
        const overflowY = getComputedStyle(node).overflowY;
        if (/(auto|scroll)/.test(overflowY)) return node;
        node = node.parentElement;
    }
    return null;
}

function scrollGroupIntoView(group) {
    const scroller = findScrollParent(group);
    if (!scroller) return;

    const scRect = scroller.getBoundingClientRect();
    const gRect = group.getBoundingClientRect();

    const gTop = gRect.top - scRect.top;
    const gBottom = gRect.bottom - scRect.top;
    const scHeight = scroller.clientHeight;

    if (gBottom <= scHeight && gTop >= 0) return;

    let delta;
    if (gBottom > scHeight) {
        delta = gBottom - scHeight;
        if (gTop - delta < 0) delta = gTop;
    } else {
        delta = gTop;
    }

    scroller.scrollBy({ top: delta, behavior: 'smooth' });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.fi-sidebar-group button');
    if (!button) return;

    const group = button.closest('.fi-sidebar-group');
    if (!group) return;

    setTimeout(() => scrollGroupIntoView(group), TRANSITION_SETTLE_MS);
});
