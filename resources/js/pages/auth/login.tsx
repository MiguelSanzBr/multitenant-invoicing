import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    demoUsers?: Array<{ email: string; company: string; role: string }>;
    demoPassword?: string;
};

export default function Login({ status, demoUsers, demoPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="voce@empresa.com.br"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Senha</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Senha"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Manter conectado</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Entrar
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {demoUsers && (
                <section
                    aria-labelledby="demo-users"
                    className="rounded-lg border p-4 text-sm"
                >
                    <h2 id="demo-users" className="mb-2 font-medium">
                        Usuários de demonstração
                    </h2>
                    <ul className="space-y-1 text-muted-foreground">
                        {demoUsers.map((user) => (
                            <li key={user.email}>
                                <span className="font-mono text-foreground">
                                    {user.email}
                                </span>{' '}
                                ({user.company}, {user.role})
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 text-muted-foreground">
                        Senha de todos:{' '}
                        <span className="font-mono text-foreground">
                            {demoPassword}
                        </span>
                    </p>
                </section>
            )}
        </>
    );
}

Login.layout = {
    title: 'Entrar na sua conta',
    description: 'Informe e-mail e senha',
};
