import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/css/refinements.css', 'resources/css/s04-s05.css', 'resources/js/app.js'],
      refresh: true,
    }),
  ],
});
