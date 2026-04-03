/**
 * Public-only Vite config — used by the Docker node-builder stage which
 * lacks vendor/ (no Composer). The full vite.config.js (including the
 * Filament admin theme) is built in the PHP stage where vendor/ exists.
 */
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/scss/public.scss',
        'resources/js/public.js',
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
