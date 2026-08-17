window.NPWidgets = window.NPWidgets || {};

// Synthetic-data generator form: per-type row counts, a running total that gates
// the submit button, and a confirmation step. Lifted out of an inline `x-data`
// at session 375 — inline method definitions are not expressible in the CSP-safe
// Alpine build the public bundle now ships, and this widget's template is
// rendered by the same renderer regardless of which surface it lands on.
window.NPWidgets.randomDataGenerator = function () {
    return {
        confirming: false,
        counts: {
            contacts: 0,
            organizations: 0,
            events: 0,
            registrations: 0,
            donations: 0,
            memberships: 0,
            posts: 0,
            products: 0,
        },

        init() {
            const initial = JSON.parse(this.$refs.config.textContent);

            for (const key of Object.keys(this.counts)) {
                this.counts[key] = initial[key] ?? 0;
            }
        },

        total() {
            return Object.values(this.counts).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
        },
    };
};
