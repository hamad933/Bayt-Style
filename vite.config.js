import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/css/refinements.css',
        'resources/css/s04-s05.css',
        'resources/css/s06.css',
        'resources/css/s07.css',
        'resources/css/s08.css',
        'resources/css/s09.css',
        'resources/css/s10.css',
        'resources/js/app.js',
      ],
      refresh: true,
    }),
  ],
});
