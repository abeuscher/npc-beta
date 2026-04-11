<button
    type="button"
    x-data="{
        fullscreen: localStorage.getItem('np-fullscreen') === '1',
        init() {
            this.apply();
            this.$watch('fullscreen', () => this.apply());
        },
        toggle() {
            this.fullscreen = ! this.fullscreen;
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
    }"
    x-on:click="toggle()"
    x-bind:aria-label="fullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
    class="fi-icon-btn relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:text-gray-500 focus-visible:bg-gray-50 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:bg-white/5"
>
    <svg
        x-show="!fullscreen"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="h-5 w-5"
    >
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
    </svg>

    <svg
        x-show="fullscreen"
        x-cloak
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="h-5 w-5"
    >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
    </svg>
</button>
