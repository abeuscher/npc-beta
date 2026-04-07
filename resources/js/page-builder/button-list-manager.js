export function buttonListManager(initialButtons, configKey) {
    return {
        buttons: Array.isArray(initialButtons) ? initialButtons : [],

        add() {
            this.buttons.push({ text: '', url: '', style: 'primary' })
            this.save()
        },
        remove(index) {
            this.buttons.splice(index, 1)
            this.save()
        },
        moveUp(index) {
            if (index === 0) return
            const item = this.buttons.splice(index, 1)[0]
            this.buttons.splice(index - 1, 0, item)
            this.save()
        },
        moveDown(index) {
            if (index >= this.buttons.length - 1) return
            const item = this.buttons.splice(index, 1)[0]
            this.buttons.splice(index + 1, 0, item)
            this.save()
        },
        save() {
            this.$wire.updateConfig(configKey, JSON.parse(JSON.stringify(this.buttons)))
        },
    }
}
