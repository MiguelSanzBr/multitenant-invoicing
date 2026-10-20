import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Login from '@/pages/auth/login';

const demoUsers = [
    { email: 'dona@acme.test', company: 'Acme', role: 'dona' },
    { email: 'dono@globex.test', company: 'Globex', role: 'dono' },
];

describe('auth/login', () => {
    it('mostra os usuários de demo com a senha quando o servidor manda', () => {
        render(<Login demoUsers={demoUsers} demoPassword="password" />);

        const demo = screen.getByRole('region', { name: 'Usuários de demonstração' });
        expect(within(demo).getByText('dona@acme.test')).toBeInTheDocument();
        expect(within(demo).getByText(/Globex/)).toBeInTheDocument();
        expect(within(demo).getByText('password')).toBeInTheDocument();
    });

    it('sem demo não mostra nada além do formulário', () => {
        render(<Login status="Sessão expirada." />);

        expect(screen.queryByRole('region', { name: 'Usuários de demonstração' })).not.toBeInTheDocument();
        expect(screen.getByLabelText('E-mail')).toBeInTheDocument();
        expect(screen.getByText('Sessão expirada.')).toBeInTheDocument();
    });
});
