window.NPWidgets = window.NPWidgets || {};

// Event Registration widget: wires the tier-quantity subtotal recalc and the
// double-submit guard for every registration form on the page. Extracted from
// the inline <script> / onsubmit= attributes in template.blade.php (session 345).
window.NPWidgets.eventRegistration = function () {
    var forms = document.querySelectorAll('form.widget-event-registration__form');

    forms.forEach(function (form) {
        // Double-submit guard (was the inline onsubmit= attribute): block a
        // second submit, then disable the button on the next tick so the first
        // submit still carries the button value.
        form.addEventListener('submit', function (e) {
            if (form._busy) {
                e.preventDefault();
                return;
            }
            form._busy = true;
            setTimeout(function () {
                var btn = form.querySelector('button[type=submit]');
                if (btn) {
                    btn.disabled = true;
                }
            }, 0);
        });

        // Tier-quantity subtotal recalc.
        var subtotalEl = form.querySelector('[data-event-registration-subtotal]');
        var inputs = form.querySelectorAll('input[data-tier-price-cents]');
        if (inputs.length === 0) {
            return;
        }

        function recalc() {
            var cents = 0;
            inputs.forEach(function (i) {
                var q = parseInt(i.value || '0', 10) || 0;
                var p = parseInt(i.getAttribute('data-tier-price-cents') || '0', 10) || 0;
                if (q < 0) q = 0;
                cents += q * p;
            });
            if (subtotalEl) {
                subtotalEl.textContent = (cents / 100).toFixed(2);
            }
        }

        inputs.forEach(function (i) { i.addEventListener('input', recalc); });
        recalc();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.NPWidgets.eventRegistration);
} else {
    window.NPWidgets.eventRegistration();
}
