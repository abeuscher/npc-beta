window.NPWidgets = window.NPWidgets || {};

// Membership signup form. The selected tier decides both where the form posts
// (paid tiers go to checkout, free tiers straight to signup) and what the submit
// button says. Both were inline IIFEs in the template until session 375 — the
// public site's CSP-safe Alpine build has no arrow functions — so they are
// getters here, fed by the JSON config block the template renders.
window.NPWidgets.portalSignup = function () {
    return {
        tierId: '',
        tiers: [],
        cfg: {},

        init() {
            this.cfg = JSON.parse(this.$refs.config.textContent);
            this.tiers = this.cfg.tiers;
            this.tierId = this.cfg.selectedTierId;
        },

        setTier(event) {
            this.tierId = event.detail.value;
        },

        get selectedTierIsPaid() {
            const tier = this.tiers.find((t) => t.id === this.tierId);

            return !!tier && tier.price > 0;
        },

        get action() {
            return this.selectedTierIsPaid ? this.cfg.checkoutUrl : this.cfg.signupUrl;
        },

        get submitLabel() {
            return this.selectedTierIsPaid ? 'Create account & pay' : 'Create account';
        },
    };
};
