import { BellRing, LayoutGrid, Receipt, Users } from 'lucide-react';
import type { NavItem } from '@/types';

export const mainNavItems: NavItem[] = [
    { title: 'Painel', href: '/dashboard', icon: LayoutGrid },
    { title: 'Clientes', href: '/clients', icon: Users },
    { title: 'Faturas', href: '/invoices', icon: Receipt },
    { title: 'Lembretes', href: '/reminders', icon: BellRing },
];
