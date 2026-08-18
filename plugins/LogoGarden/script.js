window.NPWidgets = window.NPWidgets || {};

// Logo garden — the two Swiper display modes plus the flip-card timer, all
// lifted out of inline `x-data` / `x-init` at session 375. The public site's
// CSP-safe Alpine build supports neither inline method definitions nor the
// arrow function the flip timer used, so both live here and read their
// Blade-computed settings from the JSON config block the template renders.
window.NPWidgets.logoGarden = function () {
    return {
        swiper: null,

        init() {
            if (!window.Swiper || !window.SwiperModules) return;

            const cfg = JSON.parse(this.$refs.config.textContent);
            const M = window.SwiperModules;

            const opts = {
                slidesPerView: cfg.logosPerRow,
                spaceBetween: cfg.gap,
                loop: true,
                allowTouchMove: false,
            };

            if (cfg.mode === 'smooth') {
                opts.modules = [M.Autoplay, M.FreeMode];
                opts.freeMode = true;
                opts.speed = cfg.duration;
                opts.autoplay = { delay: 0, disableOnInteraction: false };
            } else {
                opts.modules = [M.Autoplay];
                opts.autoplay = { delay: cfg.duration, disableOnInteraction: false };
            }

            this.swiper = new window.Swiper(this.$refs.container, opts);
        },
    };
};

// One flip card. The interval is per-card and read from the element, so each
// card keeps its own cadence without an expression argument.
window.NPWidgets.logoGardenFlipper = function () {
    return {
        flipped: false,
        timer: null,

        init() {
            const duration = parseInt(this.$el.dataset.flipDuration, 10) || 4000;

            this.timer = setInterval(() => {
                this.flipped = !this.flipped;
            }, duration);
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    };
};
