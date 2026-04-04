import Alpine from 'alpinejs'
import Swiper from 'swiper'
import { Navigation, Pagination, Autoplay, EffectFade, FreeMode } from 'swiper/modules'
import { calendarJs } from 'jcalendar.js/dist/calendar.export.js'
import 'jcalendar.js/dist/calendar.js.min.css'
import Chart from 'chart.js/auto'

window.Swiper = Swiper
window.SwiperModules = { Navigation, Pagination, Autoplay, EffectFade, FreeMode }
window.calendarJs = calendarJs
window.Chart = Chart

window.Alpine = Alpine

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

Alpine.start()
