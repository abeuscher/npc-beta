export function previewManager(requiredLibs) {
    return {
        presetViewport: 1920,
        zoomFactor: 1,
        libsReady: false,

        computeZoom() {
            const pane = this.$el
            const paneWidth = pane.offsetWidth
            this.zoomFactor = paneWidth > 0 ? Math.min(1, paneWidth / this.presetViewport) : 1
        },

        setViewport(w) {
            this.presetViewport = w
            this.computeZoom()
            requestAnimationFrame(() => {
                requestAnimationFrame(() => this.reinitWidgetAlpine())
            })
        },

        pinHeights() {
            const scope = this.$refs.previewScope
            if (!scope) return
            scope.querySelectorAll('.widget-preview-region').forEach(el => {
                el.style.minHeight = el.offsetHeight + 'px'
            })
        },

        unpinHeights() {
            const scope = this.$refs.previewScope
            if (!scope) return
            scope.querySelectorAll('.widget-preview-region').forEach(el => {
                el.style.minHeight = ''
            })
        },

        async loadLibs() {
            const libs = requiredLibs
            const manifest = window.__widgetLibs || {}
            const globalChecks = {
                'swiper': () => !!window.Swiper,
                'chart.js': () => !!window.Chart,
                'jcalendar': () => !!window.calendarJs,
            }

            const promises = []

            for (const lib of libs) {
                const entry = manifest[lib]
                if (!entry) continue

                if (entry.css && !document.querySelector(`link[data-widget-lib='${lib}']`)) {
                    promises.push(new Promise((resolve) => {
                        const link = document.createElement('link')
                        link.rel = 'stylesheet'
                        link.href = entry.css
                        link.dataset.widgetLib = lib
                        link.onload = resolve
                        link.onerror = () => { console.warn('Failed to load widget lib CSS:', lib); resolve() }
                        document.head.appendChild(link)
                    }))
                }

                const check = globalChecks[lib]
                const alreadyLoaded = check ? check() : document.querySelector(`script[data-widget-lib='${lib}']`)
                if (!alreadyLoaded && entry.js) {
                    promises.push(new Promise((resolve) => {
                        const script = document.createElement('script')
                        script.src = entry.js
                        script.dataset.widgetLib = lib
                        script.onload = resolve
                        script.onerror = () => { console.warn('Failed to load widget lib JS:', lib); resolve() }
                        document.head.appendChild(script)
                    }))
                }
            }

            await Promise.all(promises)
            this.libsReady = true
        },

        reinitWidgetAlpine() {
            const scope = this.$refs.previewScope
            if (!scope) return

            scope.querySelectorAll('.swiper').forEach(el => {
                if (el.swiper) el.swiper.destroy(true, true)
            })

            scope.querySelectorAll('canvas').forEach(el => {
                const chartInstance = window.Chart?.getChart?.(el)
                if (chartInstance) chartInstance.destroy()
            })

            const ignoreEls = scope.querySelectorAll('[x-ignore]')
            ignoreEls.forEach(el => {
                el.removeAttribute('x-ignore')
                delete el._x_ignore
                delete el._x_ignoreSelf
                el.setAttribute('data-x-ignore-lifted', '')
            })

            scope.querySelectorAll('[x-data]').forEach(el => {
                if (el._x_dataStack) {
                    Alpine.destroyTree(el)
                }
                Alpine.initTree(el)
            })

            scope.querySelectorAll('[data-x-ignore-lifted]').forEach(el => {
                el.removeAttribute('data-x-ignore-lifted')
                el.setAttribute('x-ignore', '')
                el._x_ignore = true
            })

            requestAnimationFrame(() => {
                scope.querySelectorAll('.swiper').forEach(el => {
                    if (el.swiper) el.swiper.update()
                })
                this.unpinHeights()
            })
        },

        async init() {
            this.computeZoom()
            await this.loadLibs()
            requestAnimationFrame(() => {
                this.computeZoom()
                requestAnimationFrame(() => this.reinitWidgetAlpine())
            })
        },

        async handlePreviewContentChanged(event) {
            const blocks = event.detail.blocks || event.detail[0]?.blocks || []
            const scope = this.$refs.previewScope
            if (!scope || !blocks.length) return

            this.pinHeights()

            let html = ''
            for (const b of blocks) {
                html += `<div class='widget-preview-region' data-widget-id='${b.id}'><div x-ignore>${b.html}</div></div>`
            }
            scope.innerHTML = html

            const self = this
            scope.querySelectorAll('.widget-preview-region').forEach(el => {
                const wid = el.dataset.widgetId
                el.addEventListener('click', (e) => {
                    e.stopPropagation()
                    self.selectedBlockId = wid
                    self.$wire.selectBlock(wid)
                })
                if (self.selectedBlockId === wid) {
                    el.classList.add('widget-preview-region--selected')
                }
            })

            await this.loadLibs()
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this.reinitWidgetAlpine()
                })
            })
        },
    }
}
