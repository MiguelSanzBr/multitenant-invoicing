// o q o HandleInertiaRequests compartilha, nada além
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'owner' | 'member';
};

export type Auth = {
    user: User;
};
