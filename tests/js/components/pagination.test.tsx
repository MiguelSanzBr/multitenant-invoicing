import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import Pagination from '@/components/pagination';

const page = (current: number, last: number, prev: string | null, next: string | null) => ({
    data: [],
    current_page: current,
    last_page: last,
    prev_page_url: prev,
    next_page_url: next,
});

it('na primeira página só mostra a próxima e onde está', () => {
    render(<Pagination page={page(1, 3, null, '/x?page=2')} />);

    expect(screen.queryByRole('link', { name: 'Anterior' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute('href', '/x?page=2');
    expect(screen.getByText('Página 1 de 3')).toBeInTheDocument();
});

it('no meio mostra as duas', () => {
    render(<Pagination page={page(2, 3, '/x?page=1', '/x?page=3')} />);

    expect(screen.getByRole('link', { name: 'Anterior' })).toHaveAttribute('href', '/x?page=1');
    expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute('href', '/x?page=3');
});

it('na última página só mostra a anterior', () => {
    render(<Pagination page={page(3, 3, '/x?page=2', null)} />);

    expect(screen.getByRole('link', { name: 'Anterior' })).toHaveAttribute('href', '/x?page=2');
    expect(screen.queryByRole('link', { name: 'Próxima' })).not.toBeInTheDocument();
});

it('sem outras páginas não renderiza nada', () => {
    const { container } = render(<Pagination page={page(1, 1, null, null)} />);

    expect(container).toBeEmptyDOMElement();
});
