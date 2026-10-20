import { router } from '@inertiajs/react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { Invoice } from '@/types/models';
import InvoicesIndex from '@/pages/invoices/index';

const invoice = (overrides: Partial<Invoice>): Invoice => ({
    id: 1,
    client_id: 2,
    description: 'Consultoria',
    amount_cents: 150000,
    currency: 'BRL',
    due_date: '2026-11-10',
    status: 'open',
    paid_at: null,
    client: { id: 2, name: 'Ana' },
    ...overrides,
});

const page = (data: Invoice[]) => ({ data, current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });

describe('invoices/index', () => {
    it('lista faturas com valor, vencimento e status', () => {
        render(<InvoicesIndex invoices={page([invoice({})])} />);

        const row = screen.getByRole('row', { name: /Consultoria/ });
        expect(within(row).getByRole('link', { name: 'Consultoria' })).toHaveAttribute('href', '/invoices/1/edit');
        expect(within(row).getByText('Ana')).toBeInTheDocument();
        expect(within(row).getByText(/1\.500,00/)).toBeInTheDocument();
        expect(within(row).getByText('10/11/2026')).toBeInTheDocument();
        expect(within(row).getByText('Em aberto')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Nova fatura' })).toHaveAttribute('href', '/invoices/create');
    });

    it('marca como paga só fatura em aberto ou vencida', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(<InvoicesIndex invoices={page([invoice({ id: 1, status: 'overdue' }), invoice({ id: 2, status: 'paid', description: 'Paga' }), invoice({ id: 3, status: 'draft', description: 'Rascunho' })])} />);

        const buttons = screen.getAllByRole('button', { name: 'Marcar como paga' });
        expect(buttons).toHaveLength(1);

        await userEvent.click(buttons[0]);
        expect(post).toHaveBeenCalledWith('/invoices/1/pay', {}, { preserveScroll: true });
    });

    it('fatura paga aparece sem link de edição', () => {
        render(<InvoicesIndex invoices={page([invoice({ status: 'paid', description: 'Paga' })])} />);

        const row = screen.getByRole('row', { name: /Paga/ });
        expect(within(row).queryByRole('link')).not.toBeInTheDocument();
        expect(within(row).getAllByText('Paga')).toHaveLength(2);
    });

    it('mostra aviso quando não tem fatura', () => {
        render(<InvoicesIndex invoices={page([])} />);

        expect(screen.getByText('Nenhuma fatura cadastrada.')).toBeInTheDocument();
    });
});
