# React workspace

React component source lives in `components/`. From the theme directory, run `npm install` once and `npm run build:hero` after changing `components/Hero.jsx`.

The build bundles React and writes the shortcode-ready entry point to `shortcodes/hero/hero.js`. WordPress enqueues that bundle only when the hero shortcode is rendered.
