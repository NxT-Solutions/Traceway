export default function Heading({
    title,
    description,
    variant = 'default',
    id,
    focusable = false,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    id?: string;
    /** The page can move focus here, such as after the control the rider used is gone. */
    focusable?: boolean;
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <h2
                id={id}
                tabIndex={focusable ? -1 : undefined}
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
