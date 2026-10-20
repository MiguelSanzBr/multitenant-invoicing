import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { Reminder } from '@/types/models';
import RemindersIndex from '@/pages/reminders/index';

const reminder = (overrides: Partial<Reminder>): Reminder => ({
    id: 1,
    invoice_id: 4,
    idempotency_key: 'invoice-4-2026-10-20',
    outcome: 'sent',
    http_status: 202,
    error: null,
    created_at: '2026-10-20T12:00:00.000000Z',
    invoice: { id: 4, description: 'Site', client: { id: 2, name: 'Ana' } },
    ...overrides,
});

const page = (data: Reminder[]) => ({ data, prev_page_url: null, next_page_url: null });

describe('reminders/index', () => {
    it('lista cada tentativa com resultado, HTTP e erro', () => {
        render(
            <RemindersIndex
                reminders={page([
                    reminder({}),
                    reminder({ id: 2, outcome: 'retry_later', http_status: 503, error: 'indisponível' }),
                    reminder({ id: 3, outcome: 'already_sent', http_status: 409 }),
                    reminder({ id: 4, outcome: 'rejected', http_status: null, error: 'recusado' }),
                ])}
            />,
        );

        const rows = screen.getAllByRole('row').slice(1);
        expect(within(rows[0]).getByText('Enviado')).toBeInTheDocument();
        expect(within(rows[0]).getByRole('link', { name: 'Site' })).toHaveAttribute('href', '/invoices/4/edit');
        expect(within(rows[0]).getByText('Ana')).toBeInTheDocument();
        expect(within(rows[0]).getByText('invoice-4-2026-10-20')).toBeInTheDocument();
        expect(within(rows[0]).getByText('20/10/2026 09:00')).toBeInTheDocument();
        expect(within(rows[1]).getByText('Vai tentar de novo')).toBeInTheDocument();
        expect(within(rows[1]).getByText('503')).toBeInTheDocument();
        expect(within(rows[1]).getByText('indisponível')).toBeInTheDocument();
        expect(within(rows[2]).getByText('Já tinha sido enviado')).toBeInTheDocument();
        expect(within(rows[3]).getByText('Recusado')).toBeInTheDocument();
        expect(within(rows[3]).getByText('sem resposta')).toBeInTheDocument();
    });

    it('mostra aviso quando não tem lembrete', () => {
        render(<RemindersIndex reminders={page([])} />);

        expect(screen.getByText('Nenhum lembrete enviado ainda.')).toBeInTheDocument();
    });
});
