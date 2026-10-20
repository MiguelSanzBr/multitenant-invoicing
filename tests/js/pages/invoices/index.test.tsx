import { router } from '@inertiajs/react';
import { act, render, screen, within } from '@testing-library/react';
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

    it('marca como paga só fatura em aberto ou vencida, depois de confirmar', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
        render(<InvoicesIndex invoices={page([invoice({ id: 1, status: 'overdue', description: 'Site' }), invoice({ id: 2, status: 'paid', description: 'Paga' }), invoice({ id: 3, status: 'draft', description: 'Rascunho' })])} />);

        expect(screen.getAllByRole('button', { name: /como paga/ })).toHaveLength(1);
        const button = screen.getByRole('button', { name: 'Marcar Site como paga' });

        await userEvent.click(button);
        expect(post).not.toHaveBeenCalled();

        await userEvent.click(button);
        expect(confirm).toHaveBeenLastCalledWith('Marcar a fatura Site como paga? Não dá pra desfazer.');
        expect(post).toHaveBeenCalledWith('/invoices/1/pay', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('trava o botão enquanto o pagamento está indo, pra não mandar duas vezes', async () => {
        let finish = () => {};
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onStart?.({} as never);
            finish = () => options?.onFinish?.({} as never);
        });
        render(<InvoicesIndex invoices={page([invoice({ id: 1, description: 'Site' }), invoice({ id: 2, description: 'Loja' })])} />);

        await userEvent.click(screen.getByRole('button', { name: 'Marcar Site como paga' }));

        expect(screen.getByRole('button', { name: 'Marcar Site como paga' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Marcar Loja como paga' })).toBeEnabled();
        await userEvent.click(screen.getByRole('button', { name: 'Marcar Site como paga' }));
        expect(post).toHaveBeenCalledTimes(1);

        act(() => finish());
        expect(screen.getByRole('button', { name: 'Marcar Site como paga' })).toBeEnabled();
    });

    it('cada status tem uma cor de badge', () => {
        render(<InvoicesIndex invoices={page([invoice({ id: 1, status: 'draft' }), invoice({ id: 2, status: 'open' }), invoice({ id: 3, status: 'paid' }), invoice({ id: 4, status: 'overdue' })])} />);

        const classes = ['Rascunho', 'Em aberto', 'Paga', 'Vencida'].map((label) => screen.getByText(label, { selector: '[data-slot="badge"]' }).className);
        expect(new Set(classes).size).toBe(4);
    });

    it('título da página é h1', () => {
        render(<InvoicesIndex invoices={page([])} />);

        expect(screen.getByRole('heading', { level: 1, name: 'Faturas' })).toBeInTheDocument();
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
