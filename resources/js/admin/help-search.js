export default () => ({
    open: false,
    focusIndex: -1,
    close() {
        this.open = false;
        this.focusIndex = -1;
    },
});
