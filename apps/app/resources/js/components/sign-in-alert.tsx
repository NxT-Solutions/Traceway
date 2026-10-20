import { CircleAlert } from 'lucide-react';
import { useRef } from 'react';
import { useFocusOnArrival } from '@/hooks/use-focus-on-arrival';

type Props = {
    message?: string;
    /**
     * Take focus once the page is ready, so a screen reader reads it before the form. Focus reads it, so it is not
     * an alert as well, which would read it twice.
     */
    focus?: boolean;
};

/**
 * Why a service did not log the rider in, and what to do next.
 */
export default function SignInAlert({ message, focus = false }: Props) {
    const alert = useRef<HTMLDivElement>(null);

    useFocusOnArrival(alert, focus && Boolean(message));

    if (!message) {
        return null;
    }

    return (
        <div
            className="notice bad"
            role={focus ? undefined : 'alert'}
            ref={alert}
            tabIndex={-1}
        >
            <CircleAlert size={20} aria-hidden="true" />
            <p>{message}</p>
        </div>
    );
}
