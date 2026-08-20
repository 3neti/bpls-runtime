import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            command:
                'APP_ENV=local STAKEHOLDER_PREVIEW_MODE=true STAKEHOLDER_PREVIEW_PROFILE=stakeholder_preview_cycle_4 STAKEHOLDER_PREVIEW_DATA_CLASSIFICATION=synthetic_only STAKEHOLDER_PREVIEW_PII_MODE=synthetic_only STAKEHOLDER_PREVIEW_PRODUCTION_MIGRATION_ENABLED=false STAKEHOLDER_PREVIEW_PRODUCTION_INTEGRATIONS=disabled php artisan wayfinder:generate',
        }),
    ],
});
