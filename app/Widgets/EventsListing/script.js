window.NPWidgets = window.NPWidgets || {};

// Static-layout (rows / day-grouped) event-type filter. Vanilla, progressive
// enhancement: rows are server-rendered and fully visible without JS. Changing
// the filter dropdown hides non-matching events, then collapses any day group
// left with no visible events and surfaces a "no matches" message when
// everything is filtered out.
function initEventsListingFilter(root) {
    const select = root.querySelector('[data-type-filter]');
    if (!select) return;

    const events = Array.from(root.querySelectorAll('[data-event-row]'));
    const dayGroups = Array.from(root.querySelectorAll('[data-day-group]'));
    const emptyMsg = root.querySelector('[data-filter-empty]');

    function apply(tag) {
        let anyVisible = false;
        events.forEach((el) => {
            const tags = (el.getAttribute('data-tags') || '').split(' ').filter(Boolean);
            const show = tag === '' || tags.includes(tag);
            el.hidden = !show;
            if (show) anyVisible = true;
        });
        dayGroups.forEach((group) => {
            group.hidden = group.querySelectorAll('[data-event-row]:not([hidden])').length === 0;
        });
        if (emptyMsg) emptyMsg.hidden = anyVisible;
    }

    select.addEventListener('change', () => apply(select.value));
}

function bootEventsListingLists() {
    document.querySelectorAll('.widget-events-listing--list').forEach((root) => {
        if (root.dataset.filterBooted) return;
        root.dataset.filterBooted = '1';
        initEventsListingFilter(root);
    });
}

if (document.readyState !== 'loading') {
    bootEventsListingLists();
} else {
    document.addEventListener('DOMContentLoaded', bootEventsListingLists);
}

window.NPWidgets.eventsListing = function () {
    return {
        swiper: null,
        search: '',
        typeFilter: '',
        cfg: null,

        init() {
            this.cfg = JSON.parse(this.$refs.listingData.textContent);
            const swiperEl = this.$refs.swiperEl;
            if (!swiperEl || !window.Swiper || !window.SwiperModules) return;

            this.swiper = new window.Swiper(swiperEl, this.buildOpts());
        },

        buildOpts() {
            const modules = [window.SwiperModules.Navigation, window.SwiperModules.Pagination];
            if (this.cfg.effect === 'fade') modules.push(window.SwiperModules.EffectFade);

            return {
                modules,
                slidesPerView: 1,
                slidesPerGroup: 1,
                spaceBetween: this.cfg.gap,
                effect: this.cfg.effect,
                fadeEffect: this.cfg.effect === 'fade' ? { crossFade: true } : undefined,
                navigation: { nextEl: this.$refs.btnNext, prevEl: this.$refs.btnPrev },
                pagination: {
                    el: this.$refs.pagination,
                    clickable: true,
                    renderBullet: (index, className) => '<span class="' + className + '">' + (index + 1) + '</span>',
                },
            };
        },

        rebuildSlides() {
            const swiperEl = this.$refs.swiperEl;
            // Guard the Swiper globals: this runs from an x-effect on init, which
            // can fire before the Swiper library has loaded (e.g. the page-builder
            // preview). buildOpts() reads window.SwiperModules unguarded, so bail
            // until the lib is present — reinitAlpine re-runs init once it loads.
            if (!swiperEl || !window.Swiper || !window.SwiperModules) return;
            const indices = this.getFilteredIndices(this.search, this.cfg.sortDefault);
            if (this.swiper) this.swiper.destroy(true, true);

            const wrapper = swiperEl.querySelector('.swiper-wrapper');
            wrapper.innerHTML = '';

            if (indices.length) {
                for (let i = 0; i < indices.length; i += this.cfg.perPage) {
                    const chunk = indices.slice(i, i + this.cfg.perPage).map(idx => this.cfg.cards[idx]);
                    wrapper.innerHTML += this.buildSlideHtml(chunk);
                }
                this.$refs.emptyMsg.style.display = 'none';
            } else {
                this.$refs.emptyMsg.style.display = '';
            }

            this.swiper = new window.Swiper(swiperEl, this.buildOpts());
        },

        buildSlideHtml(cardHtmlArray) {
            const cards = cardHtmlArray.map(html => '<article class="content-card">' + html + '</article>').join('');
            return '<div class="swiper-slide"><div class="content-grid" style="grid-template-columns:repeat(' + this.cfg.columns + ',1fr);">' + cards + '</div></div>';
        },

        getFilteredIndices(query, sortBy) {
            let indices = this.cfg.items.map((_, i) => i);
            if (query && query.trim()) {
                const q = query.toLowerCase();
                indices = indices.filter(i => {
                    const item = this.cfg.items[i];
                    return item.title.toLowerCase().includes(q)
                        || (item.location || '').toLowerCase().includes(q)
                        || (item.event_date || '').toLowerCase().includes(q);
                });
            }
            if (this.typeFilter) {
                indices = indices.filter(i => (this.cfg.items[i].tags || []).some(t => t.slug === this.typeFilter));
            }
            indices.sort((a, b) => {
                const ia = this.cfg.items[a], ib = this.cfg.items[b];
                switch (sortBy) {
                    case 'furthest':  return (ib.starts_at || '').localeCompare(ia.starts_at || '');
                    case 'title_az':  return ia.title.localeCompare(ib.title);
                    case 'title_za':  return ib.title.localeCompare(ia.title);
                    default:          return (ia.starts_at || '').localeCompare(ib.starts_at || '');
                }
            });
            return indices;
        },
    };
};
