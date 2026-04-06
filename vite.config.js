import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  build: {
    // Don't wipe public/build/ — the widgets/ subdirectory is managed
    // separately by the build server (php artisan build:public) and may
    // be owned by a different user (www-data via Docker).
    emptyOutDir: false,
  },
  plugins: [
    laravel({
      input: [
        'resources/scss/public.scss',
        'resources/js/public.js',
        'resources/css/filament/admin/theme.css',
      ],
      refresh: true,
    }),
  ],
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        silenceDeprecations: ['if-function'],
      },
    },
  },
})
