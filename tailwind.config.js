/** @type {import('tailwindcss').Config} */
// Inert root config. Tailwind runs via PostCSS on every Vite CSS input, but
// the public pipeline uses pure SCSS (no @tailwind directives) and Filament's
// theme pins its own config via @config → tailwind.config.filament.js.
export default {
  content: [],
}
