import { router, usePage } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ClientForm from './form';

const client = { id: 3, name: 'Ana', email: null, phone: '5511999990000' };

describe('clients/form', () => {
    it('cria cliente', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(<ClientForm />);

        await userEvent.type(screen.getByLabelText('Nome'), 'Bruno');
        await userEvent.type(screen.getByLabelText('E-mail'), 'b@example.com');
        await userEvent.type(screen.getByLabelText('Telefone (WhatsApp)'), '5511988887777');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(post).toHaveBeenCalledWith('/clients', { name: 'Bruno', email: 'b@example.com', phone: '5511988887777' }, expect.anything());
        expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument();
    });

    it('edita cliente existente', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        render(<ClientForm client={client} />);

        expect(screen.getByLabelText('Nome')).toHaveValue('Ana');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(put).toHaveBeenCalledWith('/clients/3', { name: 'Ana', email: '', phone: '5511999990000' }, expect.anything());
    });

    it('mostra os erros de validação', async () => {
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            options?.onError?.({ phone: 'Telefone inválido.' });
        });
        render(<ClientForm />);

        await userEvent.type(screen.getByLabelText('Nome'), 'Ana');
        await userEvent.type(screen.getByLabelText('Telefone (WhatsApp)'), '1');
        await userEvent.click(screen.getByRole('button', { name: 'Salvar' }));

        expect(await screen.findByText('Telefone inválido.')).toBeInTheDocument();
    });

    it('dono exclui depois de confirmar', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
        render(<ClientForm client={client} />);

        await userEvent.click(screen.getByRole('button', { name: 'Excluir' }));
        expect(destroy).not.toHaveBeenCalled();

        await userEvent.click(screen.getByRole('button', { name: 'Excluir' }));
        expect(confirm).toHaveBeenCalledTimes(2);
        expect(destroy).toHaveBeenCalledWith('/clients/3');
    });

    it('membro não vê o botão de excluir', () => {
        vi.mocked(usePage).mockReturnValueOnce({ props: { auth: { user: { role: 'member' } } } } as never);
        render(<ClientForm client={client} />);

        expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument();
    });
});
