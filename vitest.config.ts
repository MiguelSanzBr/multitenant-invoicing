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
            // tudo entra no gate por padrão; fica de fora só o q o Wayfinder escreve e o q veio pronto do starter kit
            include: ['resources/js/**/*.{ts,tsx}'],
            exclude: [
                'resources/js/{actions,routes,wayfinder}/**',
                'resources/js/components/ui/**',
                'resources/js/components/{app-content,app-logo,app-logo-icon,app-shell,app-sidebar-header,app-sidebar,breadcrumbs,nav-main,nav-user,password-input,user-info,user-menu-content}.tsx',
                'resources/js/hooks/**',
                'resources/js/layouts/**',
                'resources/js/types/**',
                'resources/js/lib/utils.ts',
                'resources/js/app.tsx',
            ],
            thresholds: { lines: 100, functions: 100, branches: 100, statements: 100 },
        },
    },
});
