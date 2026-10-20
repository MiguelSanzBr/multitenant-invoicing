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

export type InvoiceStatus = 'draft' | 'open' | 'paid' | 'overdue';

export type Invoice = {
    id: number;
    client_id: number;
    description: string;
    amount_cents: number;
    currency: string;
    due_date: string;
    status: InvoiceStatus;
    paid_at: string | null;
    client?: Client;
};

export type ReminderOutcome = 'sent' | 'already_sent' | 'retry_later' | 'rejected';

export type Reminder = {
    id: number;
    invoice_id: number;
    idempotency_key: string;
    outcome: ReminderOutcome;
    http_status: number | null;
    error: string | null;
    created_at: string;
    invoice: { id: number; description: string; client: { id: number; name: string } };
};
