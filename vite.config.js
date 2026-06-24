import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/budget.js',
                'resources/js/pages/category.js',
                'resources/js/pages/global-search.js',
                'resources/js/pages/invoice-detail.js',
                'resources/js/pages/issuer-favorite.js',
                'resources/js/pages/price-history.js',
                'resources/js/pages/recurring-purchase.js',
                'resources/js/pages/report.js',
                'resources/js/pages/shopping-list.js',
                'resources/js/pages/upload.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
