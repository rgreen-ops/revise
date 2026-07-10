/**
 * Forgeline — Tailwind configuration
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
          DEFAULT: "rgb(var(--fl-primary) / <alpha-value>)",
          dark: "rgb(var(--fl-primary-dark) / <alpha-value>)",
          light: "rgb(var(--fl-primary-light) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--fl-accent) / <alpha-value>)",
          dark: "rgb(var(--fl-accent-dark) / <alpha-value>)",
        },
        ink: {
          DEFAULT: "rgb(var(--fl-ink) / <alpha-value>)",
          soft: "rgb(var(--fl-ink-soft) / <alpha-value>)",
          faint: "rgb(var(--fl-ink-faint) / <alpha-value>)",
        },
        paper: "rgb(var(--fl-paper) / <alpha-value>)",
        board: "rgb(var(--fl-board) / <alpha-value>)",
        line: "rgb(var(--fl-line) / <alpha-value>)",
      },
      fontFamily: {
        display: "var(--fl-font-display)",
        body: "var(--fl-font-body)",
      },
      borderRadius: {
        card: "var(--fl-radius)",
      },
      maxWidth: {
        prose: "68ch",
      },
    },
  },
  plugins: [],
};
