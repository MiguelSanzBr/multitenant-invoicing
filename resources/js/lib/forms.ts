// liga o erro ao campo: o leitor de tela anuncia a mensagem ao focar o input
export const errorProps = (id: string, message?: string) => ({
    'aria-invalid': Boolean(message),
    'aria-describedby': message ? `${id}-error` : undefined,
});
