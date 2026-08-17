window.NPWidgets = window.NPWidgets || {};

// Coverflow product carousel. Lifted out of an inline `x-data` at session 375
// for the CSP-safe Alpine build; Blade-computed options travel in the JSON
// config block the template renders.
window.NPWidgets.productCarousel = function () {
    return {
        swiper: null,

        init() {
            if (!window.Swiper || !window.SwiperModules) return;

            const cfg = JSON.parse(this.$refs.config.textContent);
            const M = window.SwiperModules;
            const modules = [M.EffectCoverflow];

            if (cfg.navigation) modules.push(M.Navigation);
            if (cfg.pagination) modules.push(M.Pagination);
            if (cfg.autoplay) modules.push(M.Autoplay);

            const opts = {
                modules,
                effect: 'coverflow',
                centeredSlides: true,
                slidesPerView: 'auto',
                coverflowEffect: {
                    rotate: 30,
                    stretch: 0,
                    depth: 100,
                    modifier: 1,
                    slideShadows: false,
                },
                loop: cfg.loop,
            };

            if (cfg.autoplay) opts.autoplay = { delay: cfg.interval, disableOnInteraction: false };
            if (cfg.pagination) opts.pagination = { el: this.$refs.pagination, clickable: true };
            if (cfg.navigation) opts.navigation = { nextEl: this.$refs.next, prevEl: this.$refs.prev };

            this.swiper = new window.Swiper(this.$refs.container, opts);
        },
    };
};
