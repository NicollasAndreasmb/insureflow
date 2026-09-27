import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

/*
 * O Vite roda dentro do container `node`, por isso escuta em 0.0.0.0, enquanto o
 * navegador (no host) acessa o Vite por localhost na porta publicada (VITE_PORT),
 * que pode diferir da porta interna. O polling é necessário quando o código está
 * em um bind mount do Windows/macOS, que não propaga eventos de sistema de
 * arquivos para dentro do container.
 */
const hostPort = Number(process.env.VITE_PORT || 5173);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
            clientPort: hostPort,
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            interval: 1000,
            binaryInterval: 3000,
            ignored: [
                '**/vendor/**',
                '**/storage/**',
                '**/bootstrap/**',
                '**/database/**',
                '**/tests/**',
                '**/public/**',
                '**/docker/**',
                '**/.git/**',
            ],
        },
    },
});
