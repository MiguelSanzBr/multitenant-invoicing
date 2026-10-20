import { expect, it } from 'vitest';
import { mainNavItems } from '@/lib/navigation';

it('menu aponta pras telas do app', () => {
    expect(mainNavItems.map((item) => [item.title, item.href])).toEqual([
        ['Painel', '/dashboard'],
        ['Clientes', '/clients'],
        ['Faturas', '/invoices'],
        ['Lembretes', '/reminders'],
    ]);
});
