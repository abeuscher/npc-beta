window.NPWidgets = window.NPWidgets || {};

// Slide carousel. Lifted out of an inline `x-data` at session 375 — the public
// site's CSP-safe Alpine build has no inline method definitions, so the Swiper
// construction moved here and the Blade-computed options travel in the JSON
// config block the template renders (the same `x-ref` + `application/json`
// convention BlogListing and EventsListing already use).
window.NPWidgets.carousel = function () {
    return {
        swiper: null,

        init() {
            if (!window.Swiper || !window.SwiperModules) return;

            const cfg = JSON.parse(this.$refs.config.textContent);
            const M = window.SwiperModules;
            const modules = [];

            if (cfg.navigation) modules.push(M.Navigation);
            if (cfg.pagination) modules.push(M.Pagination);
            if (cfg.autoplay) modules.push(M.Autoplay);
            if (cfg.effect === 'fade') modules.push(M.EffectFade);

            const opts = {
                modules,
                slidesPerView: cfg.slidesPerView,
                spaceBetween: cfg.slidesPerView > 1 ? 16 : 0,
                loop: cfg.loop,
                speed: cfg.speed,
                effect: cfg.effect,
            };

            if (cfg.effect === 'fade') opts.fadeEffect = { crossFade: true };
            if (cfg.autoplay) opts.autoplay = { delay: cfg.interval, disableOnInteraction: false };
            if (cfg.pagination) opts.pagination = { el: this.$refs.pagination, clickable: true };
            if (cfg.navigation) opts.navigation = { nextEl: this.$refs.next, prevEl: this.$refs.prev };

            this.swiper = new window.Swiper(this.$refs.container, opts);
        },
    };
};
