import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import Dashboard from './dashboard';

it('mostra quantidade e valor por status', () => {
    render(
        <Dashboard
            totals={{
                draft: { count: 0, amount_cents: 0 },
                open: { count: 2, amount_cents: 350000 },
                paid: { count: 1, amount_cents: 700 },
                overdue: { count: 3, amount_cents: 12345 },
            }}
        />,
    );

    const open = screen.getByRole('region', { name: 'Em aberto' });
    expect(open).toHaveTextContent('2 faturas');
    expect(open).toHaveTextContent(/3\.500,00/);
    expect(screen.getByRole('region', { name: 'Paga' })).toHaveTextContent('1 fatura');
    expect(screen.getByRole('region', { name: 'Vencida' })).toHaveTextContent(/123,45/);
    expect(screen.getByRole('region', { name: 'Rascunho' })).toHaveTextContent('0 faturas');
});
