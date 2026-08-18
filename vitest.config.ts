import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/js/setup.ts'],
        include: ['tests/js/**/*.test.ts'],
        css: false,
        // The calendar and timeline bucket timestamps by *local* calendar date.
        // Without a pinned zone the same fixture lands on a different day east
        // of UTC+2 and the grouping tests fail for no reason of their own.
        env: { TZ: 'Europe/Berlin' },
    },
});
