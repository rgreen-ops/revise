/**
 * Callout — Tailwind configuration
 *
 * Colours and fonts resolve to the CSS variables defined in src/input.css
 * (the ":root" block). Rebrand the theme by editing those variables —
 * you do not need to touch this file or any HTML.
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
          DEFAULT: "rgb(var(--co-primary) / <alpha-value>)",
          dark: "rgb(var(--co-primary-dark) / <alpha-value>)",
          light: "rgb(var(--co-primary-light) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--co-accent) / <alpha-value>)",
          dark: "rgb(var(--co-accent-dark) / <alpha-value>)",
        },
        ink: {
          DEFAULT: "rgb(var(--co-ink) / <alpha-value>)",
          soft: "rgb(var(--co-ink-soft) / <alpha-value>)",
          faint: "rgb(var(--co-ink-faint) / <alpha-value>)",
        },
        paper: "rgb(var(--co-paper) / <alpha-value>)",
        board: "rgb(var(--co-board) / <alpha-value>)",
        line: "rgb(var(--co-line) / <alpha-value>)",
      },
      fontFamily: {
        display: "var(--co-font-display)",
        body: "var(--co-font-body)",
      },
      borderRadius: {
        card: "var(--co-radius)",
      },
      maxWidth: {
        prose: "68ch",
      },
    },
  },
  plugins: [],
};
