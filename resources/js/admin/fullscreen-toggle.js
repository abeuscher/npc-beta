export default () => ({
    fullscreen: localStorage.getItem('np-fullscreen') === '1',
    init() {
        this.apply();
        this.$watch('fullscreen', () => this.apply());
    },
    toggle() {
        this.fullscreen = !this.fullscreen;
    },
    apply() {
        if (this.fullscreen) {
            document.documentElement.classList.add('np-fullscreen');
            localStorage.setItem('np-fullscreen', '1');
        } else {
            document.documentElement.classList.remove('np-fullscreen');
            localStorage.setItem('np-fullscreen', '0');
        }
    },
});
