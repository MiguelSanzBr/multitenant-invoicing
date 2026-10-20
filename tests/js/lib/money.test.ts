import { describe, expect, it } from 'vitest';
import { formatBRL, parseBRL } from '@/lib/money';

describe('money', () => {
    it('formata centavos em reais', () => {
        expect(formatBRL(123456).replace(/\s/g, ' ')).toBe('R$ 1.234,56');
    });

    it.each([
        ['1.234,56', 123456],
        ['R$ 10', 1000],
        ['0,5', 50],
        [' 99,99 ', 9999],
    ])('lê %s como %i centavos', (text, cents) => {
        expect(parseBRL(text)).toBe(cents);
    });

    it.each(['', 'abc', '1,234', '-5'])('rejeita %s', (text) => {
        expect(parseBRL(text)).toBeNull();
    });
});
