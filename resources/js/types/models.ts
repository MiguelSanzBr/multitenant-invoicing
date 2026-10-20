// formato do LengthAwarePaginator do Laravel; os itens vêm dos Resources em app/Http/Resources
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

// ClientResource
export type Client = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
};

export type InvoiceStatus = 'draft' | 'open' | 'paid' | 'overdue';

// InvoiceResource (client só quando o controller carrega)
export type Invoice = {
    id: number;
    client_id: number;
    description: string;
    amount_cents: number;
    currency: string;
    due_date: string;
    status: InvoiceStatus;
    paid_at: string | null;
    client?: { id: number; name: string };
};

export type ReminderOutcome = 'sent' | 'already_sent' | 'retry_later' | 'rejected';

// ReminderResource
export type Reminder = {
    id: number;
    idempotency_key: string;
    outcome: ReminderOutcome;
    http_status: number | null;
    error: string | null;
    created_at: string;
    invoice: { id: number; description: string; client: { name: string } };
};
