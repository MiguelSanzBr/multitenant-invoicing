import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { createElement } from 'react';
import type { ReactNode } from 'react';
import { afterEach, vi } from 'vitest';

// fora de um app Inertia o Head e o Link precisam de contexto, então viram elementos simples.
// router e useForm continuam reais, os testes espionam o router
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    Link: ({ href, children }: { href: string; children: ReactNode }) =>
        createElement('a', { href }, children),
    usePage: vi.fn(() => ({ props: { auth: { user: { role: 'owner' } } } })),
}));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

// o checkbox do radix (login) usa ResizeObserver, q o jsdom não tem
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};
