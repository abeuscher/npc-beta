export function attachPasswordMismatch(opts) {
    const passwordEl = opts.passwordEl;
    const confirmEl  = opts.confirmEl;
    const formEl     = opts.formEl || null;
    const message    = opts.message || 'Passwords do not match.';
    let   hintEl     = opts.hintEl;

    if (!hintEl) {
        hintEl = document.createElement('span');
        hintEl.setAttribute('role', 'alert');
        hintEl.style.display = 'none';
        hintEl.textContent = message;
        confirmEl.parentNode.appendChild(hintEl);
    }

    function check() {
        hintEl.style.display = (confirmEl.value.length > 0 && passwordEl.value !== confirmEl.value) ? '' : 'none';
    }
    passwordEl.addEventListener('input', check);
    confirmEl.addEventListener('input', check);

    if (formEl) {
        formEl.addEventListener('submit', function (e) {
            if (passwordEl.value !== confirmEl.value) {
                e.preventDefault();
                hintEl.style.display = '';
            }
        });
    }
}

window.NPPasswordMismatch = attachPasswordMismatch;
