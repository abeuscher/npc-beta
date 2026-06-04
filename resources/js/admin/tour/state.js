// Persisted tour position. The tour walks real admin pages, so each page
// transition is a full reload — the active step has to survive in localStorage
// and resume on the next page. Non-sensitive (a tour id + step index only).

const KEY = 'np-tour';

export function getState() {
    try {
        return JSON.parse(localStorage.getItem(KEY) || 'null');
    } catch {
        return null;
    }
}

export function setState(state) {
    try {
        localStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        // localStorage unavailable — the tour still runs, just without
        // cross-page resume (single-page steps work; navigation steps stop).
    }
}

export function clearState() {
    try {
        localStorage.removeItem(KEY);
    } catch {
        // no-op
    }
}
