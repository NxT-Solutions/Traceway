import { CircleCheck } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * A short line that says something worked, such as a link sent by email. The words are in the text colour so they
 * read on paper and ink; the tick carries the marker's green beside them, never as the only sign.
 */
export default function StatusMessage({
    className,
    children,
    ...props
}: HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            role="status"
            {...props}
            className={cn(
                'flex items-start gap-2 text-sm font-medium text-foreground',
                className,
            )}
        >
            <CircleCheck
                className="mt-0.5 size-4 shrink-0 text-ring"
                aria-hidden="true"
            />
            <span>{children}</span>
        </p>
    );
}
