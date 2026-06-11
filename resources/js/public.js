import Alpine from 'alpinejs'
import Swiper from 'swiper'
import { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow, FreeMode } from 'swiper/modules'
import Chart from 'chart.js/auto'
import customSelect from './admin/custom-select.js'
import './portal/password-mismatch.js'
import { hydrate } from './shared/hydrate'

window.Swiper = Swiper
window.SwiperModules = { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow, FreeMode }
window.Chart = Chart

window.Alpine = Alpine

document.addEventListener('alpine:init', () => {
    window.Alpine.data('customSelect', customSelect)
})

Alpine.store('theme', {
    current: localStorage.getItem('theme') ?? 'auto',
    toggle() {
        this.current = this.current === 'dark' ? 'light' : 'dark'
        localStorage.setItem('theme', this.current)
        document.documentElement.setAttribute('data-theme', this.current)
    },
    init() {
        if (this.current !== 'auto') {
            document.documentElement.setAttribute('data-theme', this.current)
        }
    }
})

// Widget hydration runs through the shared routine (session 355) — the same
// `hydrate` the page-builder canvas calls per subtree. Here it is the
// whole-document first init: the libs are already bundled + assigned above and
// the customSelect component + theme store are registered, so first-init is just
// Alpine.start() walking the document.
hydrate(document, { firstInit: true })
