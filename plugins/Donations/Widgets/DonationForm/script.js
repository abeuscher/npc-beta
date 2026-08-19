window.NPWidgets = window.NPWidgets || {};

// Donation amount/frequency picker and Stripe checkout hand-off. Lifted out of
// an inline `x-data` at session 375: the public site's CSP-safe Alpine build has
// no inline method definitions, so the whole component moved here and the
// Blade-computed endpoint values travel in the JSON config block the template
// renders.
window.NPWidgets.donationForm = function () {
    return {
        amount: null,
        customAmount: '',
        showCustom: false,
        frequency: 'one_off',
        fundId: null,
        loading: false,
        error: null,
        cfg: {},

        init() {
            this.cfg = JSON.parse(this.$refs.config.textContent);
        },

        selectAmount(val) {
            this.amount = val;
            this.showCustom = false;
            this.customAmount = '';
        },

        selectCustom() {
            this.amount = null;
            this.showCustom = true;
        },

        setFund(event) {
            this.fundId = event.detail.value;
        },

        getAmount() {
            return this.showCustom ? parseFloat(this.customAmount) : this.amount;
        },

        async submit() {
            this.error = null;
            const amt = this.getAmount();

            if (!amt || isNaN(amt) || amt < 1) {
                this.error = 'Please enter an amount of at least $1.';
                return;
            }
            if (amt > 10000) {
                this.error = 'Amount cannot exceed $10,000.';
                return;
            }

            this.loading = true;

            try {
                const payload = {
                    amount: amt,
                    type: this.frequency === 'one_off' ? 'one_off' : 'recurring',
                    frequency: this.frequency === 'one_off' ? null : this.frequency,
                };

                if (this.cfg.successPage) payload.success_page = this.cfg.successPage;
                if (this.fundId) payload.fund_id = this.fundId;

                const res = await fetch(this.cfg.checkoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? this.cfg.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (data.url) {
                    window.location.href = data.url;
                } else {
                    this.error = data.error ?? 'Something went wrong. Please try again.';
                    this.loading = false;
                }
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
                this.loading = false;
            }
        },
    };
};
