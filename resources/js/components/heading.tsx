// título da página: um h1 por tela
export default function Heading({ title, description }: { title: string; description?: string }) {
    return (
        <header className="mb-8 space-y-0.5">
            <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
            {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </header>
    );
}
