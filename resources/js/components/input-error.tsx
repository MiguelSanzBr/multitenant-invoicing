import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

// com `id`, o campo aponta pra cá via aria-describedby (ver lib/forms.ts)
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn('text-sm text-red-600 dark:text-red-400', className)}
        >
            {message}
        </p>
    ) : null;
}
