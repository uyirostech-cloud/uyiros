/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eef7f6',
          100: '#d3ecea',
          200: '#a8d9d5',
          300: '#74c0ba',
          400: '#45a29c',
          500: '#2a8781',
          600: '#1f6c68',
          700: '#1b5754',
          800: '#194745',
          900: '#173b3a',
        },
        ink: {
          50: '#f7f8f9',
          100: '#eceef1',
          200: '#d5dae1',
          300: '#b1bac6',
          400: '#8794a6',
          500: '#69778b',
          600: '#535f72',
          700: '#444d5c',
          800: '#3a424e',
          900: '#333944',
        },
      },
      fontFamily: {
        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(16,24,40,0.05), 0 1px 3px rgba(16,24,40,0.06)',
        overlay: '0 8px 28px rgba(16,24,40,0.12)',
      },
    },
  },
  plugins: [],
};
