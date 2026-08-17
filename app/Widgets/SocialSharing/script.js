window.NPWidgets = window.NPWidgets || {};

// "Copy link" button — copies the current URL and flashes a confirmation for two
// seconds. Lifted out of an inline handler at session 375: the public site's
// CSP-safe Alpine build has no arrow functions and cannot reach `navigator` or
// `window` from a template expression.
window.NPWidgets.socialSharingCopy = function () {
    return {
        copied: false,
        resetTimer: null,

        copy() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.copied = true;
                clearTimeout(this.resetTimer);
                this.resetTimer = setTimeout(() => {
                    this.copied = false;
                }, 2000);
            });
        },

        destroy() {
            clearTimeout(this.resetTimer);
        },
    };
};
