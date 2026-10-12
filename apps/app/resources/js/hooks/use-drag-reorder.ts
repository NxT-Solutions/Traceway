import {
    type PointerEvent as ReactPointerEvent,
    useLayoutEffect,
    useRef,
} from 'react';

/**
 * Reorders a list by dragging a row's grip, or with the arrow keys through
 * `move`. Each row is a `.pt` with `data-point` set to the item id, directly
 * inside the element on `listRef`. While a row is dragged its neighbours
 * slide out of the way. When the list changes, every row glides from where
 * it was to where it lands.
 */
export function useDragReorder(
    ids: string[],
    onMove: (from: number, to: number) => void,
) {
    const listRef = useRef<HTMLDivElement>(null);
    const shiftRef = useRef<Map<string, number> | null>(null);
    const order = ids.join('\n');

    useLayoutEffect(() => {
        const before = shiftRef.current;
        const list = listRef.current;
        const known = new Set(order.split('\n'));
        shiftRef.current = null;

        if (before === null || list === null) {
            return;
        }

        const nodes = [...list.querySelectorAll<HTMLElement>('[data-point]')];
        const shifts: Array<{ node: HTMLElement; dy: number }> = [];

        for (const node of nodes) {
            const id = node.dataset.point;

            if (id === undefined || !known.has(id)) {
                continue;
            }

            const top = before.get(id);

            if (top === undefined) {
                continue;
            }

            node.style.transition = 'none';
            node.style.transform = '';
            const dy = top - node.getBoundingClientRect().top;

            if (Math.abs(dy) < 1) {
                node.style.transition = '';

                continue;
            }

            node.style.transform = `translateY(${dy}px)`;
            shifts.push({ node, dy });
        }

        if (shifts.length === 0) {
            return;
        }

        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        requestAnimationFrame(() => {
            for (const { node } of shifts) {
                node.style.transition = reduced
                    ? 'none'
                    : 'transform 180ms var(--ease)';
                node.style.transform = '';
            }
        });
    }, [order]);

    function move(from: number, to: number): void {
        const list = listRef.current;

        if (
            from === to ||
            from < 0 ||
            to < 0 ||
            from >= ids.length ||
            to >= ids.length
        ) {
            list?.querySelectorAll<HTMLElement>('[data-point]').forEach(
                (node) => {
                    node.style.transform = '';
                    node.style.transition = '';
                    node.classList.remove('lift');
                },
            );

            return;
        }

        if (list !== null) {
            const before = new Map<string, number>();

            list.querySelectorAll<HTMLElement>('[data-point]').forEach(
                (node) => {
                    const id = node.dataset.point;

                    if (id !== undefined) {
                        before.set(id, node.getBoundingClientRect().top);
                    }

                    node.style.transform = '';
                    node.style.transition = '';
                    node.classList.remove('lift');
                },
            );
            shiftRef.current = before;
        }

        onMove(from, to);
    }

    function startDrag(event: ReactPointerEvent<HTMLButtonElement>): void {
        if (event.button !== 0 || ids.length < 2) {
            return;
        }

        const grip = event.currentTarget;
        const row = grip.closest('.pt');
        const list = listRef.current;

        if (!(row instanceof HTMLElement) || list === null) {
            return;
        }

        event.preventDefault();
        grip.setPointerCapture(event.pointerId);

        const rows = [
            ...list.querySelectorAll<HTMLElement>(':scope > .pt[data-point]'),
        ];
        const origin = rows.indexOf(row);

        if (origin < 0) {
            return;
        }

        const tops = rows.map((item) => item.getBoundingClientRect().top);
        const height = row.getBoundingClientRect().height;
        const above = tops[0];
        const below = tops[1];
        const pitch =
            above !== undefined && below !== undefined
                ? below - above
                : height + 8;
        let slot = origin;
        row.classList.add('lift');

        const place = (clientY: number) => {
            const delta = clientY - event.clientY;
            const clamped = Math.max(
                -origin * pitch,
                Math.min((rows.length - 1 - origin) * pitch, delta),
            );
            row.style.transform = `translateY(${clamped}px)`;
            const nextSlot = Math.max(
                0,
                Math.min(rows.length - 1, Math.round(origin + clamped / pitch)),
            );

            if (nextSlot === slot) {
                return;
            }

            slot = nextSlot;

            rows.forEach((item, index) => {
                if (item === row) {
                    return;
                }

                let shift = 0;

                if (origin < slot && index > origin && index <= slot) {
                    shift = -pitch;
                }

                if (slot < origin && index >= slot && index < origin) {
                    shift = pitch;
                }

                item.style.transform =
                    shift === 0 ? '' : `translateY(${shift}px)`;
            });
        };

        const follow = (pointer: PointerEvent) => {
            place(pointer.clientY);
        };
        const end = (pointer: PointerEvent) => {
            grip.removeEventListener('pointermove', follow);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            place(pointer.clientY);
            move(origin, slot);
        };

        grip.addEventListener('pointermove', follow);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    }

    return { listRef, move, startDrag };
}
