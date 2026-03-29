/** @type {import('tailwindcss').Config} */
export default {
  content: [
    'resources/views/**/*.blade.php',
    'app/Livewire/**/*.php',
  ],
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        primary: 'var(--color-primary, #0172ad)',
      },
      fontFamily: {
        heading: 'var(--font-family-heading, system-ui, sans-serif)',
        body: 'var(--font-family-body, system-ui, sans-serif)',
      },
      borderRadius: {
        DEFAULT: '0.25rem',
      },
      maxWidth: {
        container: '80rem',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
