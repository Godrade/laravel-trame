import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// Builds the editor (editor.js + editor.css) and the iframe bridge (frame.js)
// into dist/ with stable names. Served by the package's asset route.
export default defineConfig({
    plugins: [tailwindcss()],
    publicDir: false,
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        target: 'es2020',
        cssCodeSplit: true,
        rollupOptions: {
            input: {
                editor: 'resources/js/editor/main.js',
                frame: 'resources/js/frame/frame.js',
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
        },
    },
});
