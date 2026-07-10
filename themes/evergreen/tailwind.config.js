/**
 * Terrace — Tailwind configuration
 *
 * Colours and fonts resolve to the CSS variables defined in src/input.css
 * (the ":root" block). Recolour the theme for your club by editing those
 * variables — you do not need to touch this file or any HTML.
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./*.html", "./assets/js/**/*.js"],
  theme: {
    container: {
      center: true,
      padding: { DEFAULT: "1.25rem", lg: "2rem" },
      screens: { sm: "640px", md: "768px", lg: "1024px", xl: "1200px" },
    },
    extend: {
      colors: {
        primary: {
          DEFAULT: "rgb(var(--tc-primary) / <alpha-value>)",
          dark: "rgb(var(--tc-primary-dark) / <alpha-value>)",
          light: "rgb(var(--tc-primary-light) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--tc-accent) / <alpha-value>)",
          dark: "rgb(var(--tc-accent-dark) / <alpha-value>)",
        },
        ink: {
          DEFAULT: "rgb(var(--tc-ink) / <alpha-value>)",
          soft: "rgb(var(--tc-ink-soft) / <alpha-value>)",
          faint: "rgb(var(--tc-ink-faint) / <alpha-value>)",
        },
        paper: "rgb(var(--tc-paper) / <alpha-value>)",
        board: "rgb(var(--tc-board) / <alpha-value>)",
        line: "rgb(var(--tc-line) / <alpha-value>)",
        win: "rgb(var(--tc-win) / <alpha-value>)",
        loss: "rgb(var(--tc-loss) / <alpha-value>)",
        draw: "rgb(var(--tc-draw) / <alpha-value>)",
      },
      fontFamily: {
        display: "var(--tc-font-display)",
        body: "var(--tc-font-body)",
      },
      borderRadius: {
        card: "var(--tc-radius)",
      },
      maxWidth: {
        prose: "68ch",
      },
    },
  },
  plugins: [],
};
