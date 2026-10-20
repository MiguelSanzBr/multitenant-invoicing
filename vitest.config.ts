import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['tests/js/setup.ts'],
        // fora de resources/js pq o glob de páginas do Inertia pegaria os testes no build
        include: ['tests/js/**/*.test.{ts,tsx}'],
        coverage: {
            provider: 'v8',
            // só o código escrito neste repo, o resto é do starter kit (lista no README)
            include: [
                'resources/js/components/pagination.tsx',
                'resources/js/pages/dashboard.tsx',
                'resources/js/pages/clients/**',
                'resources/js/pages/invoices/**',
                'resources/js/pages/reminders/**',
                'resources/js/lib/invoices.ts',
                'resources/js/lib/money.ts',
                'resources/js/lib/navigation.ts',
            ],
            thresholds: { lines: 100, functions: 100, branches: 100, statements: 100 },
        },
    },
});
