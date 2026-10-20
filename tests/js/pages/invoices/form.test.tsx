import { router, usePage } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { Invoice } from '@/types/models';
import InvoiceForm from '@/pages/invoices/form';

const clients = [
    { id: 2, name: 'Ana' },
    { id: 5, name: 'Bruno' },
];

const invoice: Invoice = {
    id: 9,
    client_id: 5,
    description: 'Consultoria',
    amount_cents: 150000,
    currency: 'BRL',
    due_date: '2026-11-10',
    status: 'draft',
    paid_at: null,
};

describe('invoices/form', () => {
    it('cria fatura convertendo reais pra centavos', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(<InvoiceForm clients={clients} />);

        await userEvent.selectOptions(screen.getByLabelText('Cliente'), '5');
        await userEvent.type(screen.getByLabelText('Descrição'), 'Site');
        await userEvent.type(screen.getByLabelText('Valor (R$)'), '1.234,56');
        await userEvent.type(screen.getByLabelText('Vencimento'), '2026-12-01');
        await userEvent.selectOptions(screen.getByLabelText('Status'), 'open');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(post).toHaveBeenCalledWith(
            '/invoices',
            { client_id: '5', description: 'Site', amount_cents: 123456, due_date: '2026-12-01', status: 'open' },
            expect.anything(),
        );
    });

    it('avisa valor inválido sem enviar', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(<InvoiceForm clients={clients} />);

        await userEvent.selectOptions(screen.getByLabelText('Cliente'), '2');
        await userEvent.type(screen.getByLabelText('Descrição'), 'Site');
        await userEvent.type(screen.getByLabelText('Valor (R$)'), 'abc');
        await userEvent.type(screen.getByLabelText('Vencimento'), '2026-12-01');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(screen.getByText('Valor inválido.')).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('edita fatura e mostra erro do servidor', async () => {
        vi.spyOn(router, 'put').mockImplementation((url, data, options) => {
            expect(url).toBe('/invoices/9');
            expect(data).toMatchObject({ client_id: '5', amount_cents: 150000, due_date: '2026-11-10', status: 'draft' });
            options?.onError?.({ due_date: 'Data inválida.' });
        });
        render(<InvoiceForm clients={clients} invoice={invoice} />);

        expect(screen.getByLabelText('Valor (R$)')).toHaveValue('1500,00');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(await screen.findByText('Data inválida.')).toBeInTheDocument();
    });

    it('vencida edita sem select de status e sem mandar status', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        render(<InvoiceForm clients={clients} invoice={{ ...invoice, status: 'overdue' }} />);

        expect(screen.queryByLabelText('Status')).not.toBeInTheDocument();
        expect(screen.getByText(/Vencida\. Se o vencimento for pra hoje ou depois/)).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(put).toHaveBeenCalledWith(
            '/invoices/9',
            { client_id: '5', description: 'Consultoria', amount_cents: 150000, due_date: '2026-11-10' },
            expect.anything(),
        );
    });

    it('paga fica só de leitura, sem salvar', () => {
        render(<InvoiceForm clients={clients} invoice={{ ...invoice, status: 'paid', paid_at: '2026-10-20T13:00:00+00:00' }} />);

        expect(screen.getByText('Fatura paga não pode ser editada.')).toBeInTheDocument();
        expect(screen.getByLabelText('Descrição')).toBeDisabled();
        expect(screen.queryByRole('button', { name: 'Salvar' })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Excluir' })).toBeEnabled();
    });

    it('dono exclui depois de confirmar, membro não vê o botão', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const { unmount } = render(<InvoiceForm clients={clients} invoice={invoice} />);

        await userEvent.click(screen.getByRole('button', { name: 'Excluir' }));
        expect(destroy).toHaveBeenCalledWith('/invoices/9');
        unmount();

        vi.mocked(usePage).mockReturnValueOnce({ props: { auth: { user: { role: 'member' } } } } as never);
        render(<InvoiceForm clients={clients} invoice={invoice} />);
        expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument();
    });

    it('não exclui sem confirmar', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(<InvoiceForm clients={clients} invoice={invoice} />);

        await userEvent.click(screen.getByRole('button', { name: 'Excluir' }));

        expect(destroy).not.toHaveBeenCalled();
    });
});
