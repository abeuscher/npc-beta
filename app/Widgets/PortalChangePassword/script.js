document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.NPPasswordMismatch !== 'function') return;
    var form = document.getElementById('portal-pw-form');
    if (!form) return;
    var pw   = document.getElementById('ppw_new');
    var conf = document.getElementById('ppw_confirm');
    var err  = document.getElementById('ppw-match-error');
    if (!pw || !conf || !err) return;
    window.NPPasswordMismatch({ passwordEl: pw, confirmEl: conf, hintEl: err, formEl: form });
});
