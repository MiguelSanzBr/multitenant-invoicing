import { Link } from '@inertiajs/react';

type Props = { prev: string | null; next: string | null };

export default function Pagination({ prev, next }: Props) {
    if (!prev && !next) {
        return null;
    }

    return (
        <nav aria-label="Paginação" className="flex justify-between text-sm">
            {prev ? <Link href={prev}>Anterior</Link> : <span />}
            {next && <Link href={next}>Próxima</Link>}
        </nav>
    );
}
