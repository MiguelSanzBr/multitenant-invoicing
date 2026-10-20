const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export const formatBRL = (cents: number): string => brl.format(cents / 100);

export const centsToInput = (cents: number): string => (cents / 100).toFixed(2).replace('.', ',');

// aceita "1.234,56", "R$ 10" e "0,5". Ponto é milhar e vírgula é decimal, o resto é inválido
export function parseBRL(text: string): number | null {
    const clean = text.replace(/R\$|\s/g, '').replace(/\./g, '').replace(',', '.');

    return /^\d+(\.\d{1,2})?$/.test(clean) ? Math.round(Number(clean) * 100) : null;
}
