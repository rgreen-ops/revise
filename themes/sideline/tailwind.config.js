/**
 * Sideline — Tailwind configuration
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
          DEFAULT: "rgb(var(--sl-primary) / <alpha-value>)",
          dark: "rgb(var(--sl-primary-dark) / <alpha-value>)",
          light: "rgb(var(--sl-primary-light) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--sl-accent) / <alpha-value>)",
          dark: "rgb(var(--sl-accent-dark) / <alpha-value>)",
        },
        ink: {
          DEFAULT: "rgb(var(--sl-ink) / <alpha-value>)",
          soft: "rgb(var(--sl-ink-soft) / <alpha-value>)",
          faint: "rgb(var(--sl-ink-faint) / <alpha-value>)",
        },
        paper: "rgb(var(--sl-paper) / <alpha-value>)",
        board: "rgb(var(--sl-board) / <alpha-value>)",
        line: "rgb(var(--sl-line) / <alpha-value>)",
        boys: "rgb(var(--sl-boys) / <alpha-value>)",
        girls: "rgb(var(--sl-girls) / <alpha-value>)",
        mixed: "rgb(var(--sl-mixed) / <alpha-value>)",
      },
      fontFamily: {
        display: "var(--sl-font-display)",
        body: "var(--sl-font-body)",
      },
      borderRadius: {
        card: "var(--sl-radius)",
      },
      maxWidth: {
        prose: "68ch",
      },
    },
  },
  plugins: [],
};
