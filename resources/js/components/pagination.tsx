import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/models';

export default function Pagination({ page }: { page: Paginated<unknown> }) {
    if (!page.prev_page_url && !page.next_page_url) {
        return null;
    }

    return (
        <nav aria-label="Paginação" className="flex items-center justify-between gap-2 text-sm">
            {page.prev_page_url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={page.prev_page_url}>Anterior</Link>
                </Button>
            ) : (
                <span />
            )}
            <span className="text-muted-foreground">
                Página {page.current_page} de {page.last_page}
            </span>
            {page.next_page_url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={page.next_page_url}>Próxima</Link>
                </Button>
            ) : (
                <span />
            )}
        </nav>
    );
}
