import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig, lazyPlugins } from 'vite-plus';

/**
 * Files outside the Vite build that the service worker precaches, versioned by content.
 */
const precachedPublicFiles = [
    'offline.html',
    'icons/icon-192.png',
    'icons/badge-96.png',
].map((file) => ({
    url: `/${file}`,
    revision: createHash('md5')
        .update(readFileSync(`public/${file}`))
        .digest('hex'),
}));

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Call times and headings on the volunteer side: a condensed,
                // broadcast-graphics face with tabular figures.
                bunny('Saira Semi Condensed', {
                    weights: [600, 700],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        // Installable app and Web Push (research R11). The worker and manifest
        // are written to public/ so they are served from the site root with
        // scope "/"; the precached assets live under /build/.
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            outDir: 'public',
            base: '/',
            scope: '/',
            buildBase: '/build/',
            injectRegister: false,
            includeManifestIcons: false,
            manifest: {
                id: '/',
                name: 'Cue App',
                short_name: 'Cue',
                description: 'Escalas e turnos da equipa de voluntários.',
                lang: 'pt',
                dir: 'ltr',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                orientation: 'portrait',
                background_color: '#ffffff',
                theme_color: '#142f5c',
                icons: [
                    {
                        src: '/icons/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/maskable-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/icons/maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
                shortcuts: [
                    { name: 'A minha escala', url: '/minha-escala' },
                    { name: 'Trocas', url: '/trocas' },
                ],
            },
            injectManifest: {
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,woff2}'],
                modifyURLPrefix: { '': '/build/' },
                additionalManifestEntries: precachedPublicFiles,
                rollupFormat: 'iife',
            },
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['resources/js/test/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
    lint: {
        // Defaults plus jsx-a11y for the WCAG 2.1 AA requirement (FR-041).
        plugins: ['react', 'typescript', 'unicorn', 'oxc', 'jsx-a11y'],
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            // Built output is committed for deployment; never reformat it.
            'public/**',
            '.github/**',
            '.specify/**',
            '.claude/**',
            'specs/**',
            'README.md',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
