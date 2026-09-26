import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

/*
 * Configuração dos testes do frontend (Vitest + @vue/test-utils em jsdom).
 * Separada do vite.config.js para não carregar o plugin do Laravel, que só faz
 * sentido no servidor de desenvolvimento e no build.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/tests/**/*.test.js'],
        restoreMocks: true,
        // Poucos workers: carregar o jsdom a partir de um bind mount do Windows é
        // lento, e muitos workers simultâneos estouram o tempo de inicialização.
        maxWorkers: 2,
    },
});
