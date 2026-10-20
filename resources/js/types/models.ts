export type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type Client = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
};
