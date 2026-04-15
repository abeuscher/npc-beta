window.NPWidgets = window.NPWidgets || {};

window.NPWidgets.eventsListing = function () {
    return {
        swiper: null,
        search: '',
        cfg: null,

        init() {
            this.cfg = JSON.parse(this.$refs.listingData.textContent);
            const swiperEl = this.$refs.swiperEl;
            if (!swiperEl || !window.Swiper) return;

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
            const indices = this.getFilteredIndices(this.search, this.cfg.sortDefault);
            const swiperEl = this.$refs.swiperEl;
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
                        || item.date.toLowerCase().includes(q);
                });
            }
            indices.sort((a, b) => {
                const ia = this.cfg.items[a], ib = this.cfg.items[b];
                switch (sortBy) {
                    case 'furthest':  return (ib.date_iso || '').localeCompare(ia.date_iso || '');
                    case 'title_az':  return ia.title.localeCompare(ib.title);
                    case 'title_za':  return ib.title.localeCompare(ia.title);
                    default:          return (ia.date_iso || '').localeCompare(ib.date_iso || '');
                }
            });
            return indices;
        },
    };
};
