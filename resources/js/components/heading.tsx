// variante default é o título da página (h1); small é subtítulo de seção (h2)
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    const Tag = variant === 'small' ? 'h2' : 'h1';

    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <Tag
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </Tag>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
