import { readFileSync, readdirSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// The pre-Laravel page scripts and stylesheets. Each file is its own entry so
// pages keep loading exactly the files they loaded before, as classic
// <script>/<link> tags via Vite::asset() (see resources/views/components).
// The scripts only attach to `window` and import nothing, so each built file
// is still a plain script that runs in document order.
const legacy = (dir, ext) =>
    readdirSync(dir)
        .filter((f) => f.endsWith(ext))
        .map((f) => `${dir}/${f}`);

// The bundler treats entries as ES modules (always strict) and drops their
// 'use strict' directives, but the legacy files run as classic scripts, so
// put the directive back on any file that had one.
const keepUseStrict = {
    name: 'legacy-keep-use-strict',
    apply: 'build',
    // generateBundle runs after minification, which would strip it again.
    generateBundle(options, bundle) {
        for (const chunk of Object.values(bundle)) {
            const id = (chunk.facadeModuleId || '').replace(/\\/g, '/');
            if (chunk.type === 'chunk' && id.includes('/resources/js/legacy/') && /['"]use strict['"]/.test(readFileSync(id, 'utf8'))) {
                chunk.code = `"use strict";${chunk.code}`;
            }
        }
    },
};

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // real PDF files of a curriculum (a module: it bundles jsPDF)
                'resources/js/curriculum-pdf.js',
                // Excel curriculum template and import (a module: it loads ExcelJS when used)
                'resources/js/curriculum-excel.js',
                ...legacy('resources/js/legacy', '.js'),
                ...legacy('resources/css/legacy', '.css'),
            ],
            refresh: true,
            fonts: [
                // DM Sans is the typeface the UI already uses (previously pulled
                // from the Google Fonts CDN); bunny() downloads it at build time
                // so the app has no external font dependency.
                bunny('DM Sans', {
                    weights: [400, 500, 600, 700],
                    subsets: ['latin'],
                }),
            ],
        }),
        tailwindcss(),
        keepUseStrict,
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
