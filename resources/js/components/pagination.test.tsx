import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import Pagination from './pagination';

it('na primeira página só mostra a próxima', () => {
    render(<Pagination prev={null} next="/x?page=2" />);

    expect(screen.queryByRole('link', { name: 'Anterior' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Próxima' })).toHaveAttribute('href', '/x?page=2');
});

it('sem outras páginas não renderiza nada', () => {
    const { container } = render(<Pagination prev={null} next={null} />);

    expect(container).toBeEmptyDOMElement();
});
