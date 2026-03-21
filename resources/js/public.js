import Alpine from 'alpinejs'

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
