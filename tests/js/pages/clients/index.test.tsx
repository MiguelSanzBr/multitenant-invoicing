import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import ClientsIndex from '@/pages/clients/index';

const page = (data: Array<{ id: number; name: string; email: string | null; phone: string }>, prev: string | null = null, next: string | null = null) => ({
    data,
    current_page: 1,
    last_page: 1,
    prev_page_url: prev,
    next_page_url: next,
});

describe('clients/index', () => {
    it('lista os clientes com link pra editar', () => {
        render(<ClientsIndex clients={page([{ id: 7, name: 'Ana', email: 'ana@example.com', phone: '5511999990000' }])} />);

        expect(screen.getByRole('link', { name: 'Ana' })).toHaveAttribute('href', '/clients/7/edit');
        expect(screen.getByText('ana@example.com')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Novo cliente' })).toHaveAttribute('href', '/clients/create');
    });

    it('mostra aviso quando não tem cliente', () => {
        render(<ClientsIndex clients={page([])} />);

        expect(screen.getByText('Nenhum cliente cadastrado.')).toBeInTheDocument();
    });

    it('mostra a paginação quando tem mais páginas', () => {
        render(<ClientsIndex clients={page([{ id: 1, name: 'Ana', email: null, phone: '1' }], '/clients?page=1', '/clients?page=3')} />);

        expect(screen.getByRole('link', { name: 'Anterior' })).toHaveAttribute('href', '/clients?page=1');
        expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute('href', '/clients?page=3');
    });
});
