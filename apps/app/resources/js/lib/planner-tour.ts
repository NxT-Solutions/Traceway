import { type DriveStep, driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../../css/planner-tour.css';

/**
 * One stop on the tour. `panel` says whether the plan panel must be open to
 * see it: on a phone or tablet the panel is a drawer, closed for the map and
 * the header, open for what is inside it.
 */
type TourStop = {
    element?: string;
    /** A smaller part to point at on a short screen, such as a phone on its side. */
    short?: string;
    title: string;
    description: string;
    panel: boolean;
};

/** The stops, in order. A stop whose element is not on the page is left out. */
export const tourStops: TourStop[] = [
    {
        title: 'Welcome to Traceway',
        description:
            'Plan a route here, then take it with you on your watch, GPS or bike computer. This short tour shows you how.',
        panel: false,
    },
    {
        element: '.tw-planner [role="search"]',
        title: 'Find a place',
        description:
            'Type a town, a street or a park. Your route starts there.',
        panel: false,
    },
    {
        element: '[data-tour="map"]',
        title: 'Or tap the map',
        description:
            'Tap anywhere to start from that spot. Drag to move the map. Pinch or scroll to zoom.',
        panel: false,
    },
    {
        element: '[data-tour="modes"]',
        title: 'Three ways to plan',
        description:
            'Generate makes a loop for you. Build follows the points you tap. Multisport joins legs, like a triathlon.',
        panel: true,
    },
    {
        element: '[data-tour="ask"]',
        short: '[data-tour="ask"] .row:nth-of-type(4)',
        title: 'Say what you want',
        description:
            'Choose your sport, how far, the surface and the hills. Tap a line to change it.',
        panel: true,
    },
    {
        element: '[data-tour="draw"]',
        title: 'Draw your route',
        description:
            'Traceway finds a route on real roads and paths. Not quite right? Draw again for another.',
        panel: true,
    },
    {
        element: '[data-tour="send"]',
        title: 'Take it with you',
        description:
            'Send the route to your Garmin, or download a file for any watch, GPS or bike computer.',
        panel: true,
    },
    {
        element: '[data-tour="saved"]',
        title: 'Your saved routes',
        description:
            'Save a route you like. You find all of them here, on any device.',
        panel: false,
    },
    {
        element: '[data-tour="account"]',
        title: 'Your account',
        description:
            'Your name and sign-in, your devices, and a light or dark look.',
        panel: false,
    },
    {
        element: '[data-tour="help"]',
        title: 'Need it again?',
        description:
            'Tap the question mark to see this tour again, any time. Happy planning.',
        panel: false,
    },
];

export type PlannerTour = { stop: () => void; isActive: () => boolean };

type TourOptions = {
    /** Opens or closes the plan panel, at once, before a stop is shown. */
    showPanel: (open: boolean) => void;
    /** Runs once, when the tour ends: finished, closed, or stopped. */
    onEnd: () => void;
};

/** How long the panel drawer takes to open or close, in planner.css. */
const drawerMs = 180;

function reducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Shows a rider around the planner, one stop at a time. Works with a mouse,
 * a finger and the keyboard (arrows, Enter, Escape), on any screen size.
 */
export function startPlannerTour({
    showPanel,
    onEnd,
}: TourOptions): PlannerTour {
    const stops = tourStops.filter(
        (stop) =>
            stop.element === undefined ||
            document.querySelector(stop.element) !== null,
    );
    const animate = !reducedMotion();
    let waiting = 0;
    let ended = false;

    /**
     * Ends the tour once, however it ends. driver.js skips its own end hook
     * when the tour is closed before the first stop has settled.
     */
    function end(): void {
        window.clearTimeout(waiting);

        if (tour.isActive()) {
            tour.destroy();
        }

        if (ended) {
            return;
        }

        ended = true;
        onEnd();
    }

    const short = window.matchMedia('(max-height: 560px)').matches;
    const steps: DriveStep[] = stops.map((stop, index) => ({
        element: (short ? stop.short : undefined) ?? stop.element,
        popover: {
            title: stop.title,
            description: stop.description,
            showButtons:
                index === 0 ? ['next', 'close'] : ['next', 'previous', 'close'],
            nextBtnText:
                index === 0
                    ? 'Show me'
                    : index === stops.length - 1
                      ? 'Start planning'
                      : 'Next',
        },
    }));

    const tour = driver({
        steps,
        animate,
        smoothScroll: animate,
        allowClose: true,
        overlayClickBehavior: 'none',
        overlayColor: 'oklch(0.16 0.03 200)',
        overlayOpacity: 0.62,
        stagePadding: 6,
        stageRadius: 12,
        popoverOffset: 12,
        disableActiveInteraction: true,
        showProgress: true,
        progressText: '{{current}} of {{total}}',
        prevBtnText: 'Back',
        closeBtnLabel: 'Close the tour',
        popoverClass: 'tw-tour',
        onPopoverRender: (popover) => {
            const theme = document
                .querySelector('.tw-planner')
                ?.getAttribute('data-theme');
            popover.wrapper.setAttribute('data-theme', theme ?? 'light');
            // driver.js focuses the close button first. Enter should go on, not end the tour.
            queueMicrotask(() => popover.nextButton.focus());
        },
        onNextClick: () => go((tour.getActiveIndex() ?? 0) + 1),
        onPrevClick: () => go((tour.getActiveIndex() ?? 0) - 1),
        onDestroyStarted: end,
        onDestroyed: end,
    });

    /**
     * Opens or closes the panel for the next stop, then shows it once the
     * drawer has stopped moving, so the light lands on the right spot.
     */
    function go(index: number): void {
        if (index < 0) {
            return;
        }

        const stop = stops[index];

        if (stop === undefined) {
            end();

            return;
        }

        const drawer = document.querySelector('.tw-planner .panel');
        const wasOpen = drawer?.classList.contains('open') ?? false;
        showPanel(stop.panel);
        const moves =
            animate &&
            wasOpen !== stop.panel &&
            !window.matchMedia('(min-width: 1024px)').matches;

        window.clearTimeout(waiting);

        if (!moves) {
            tour.moveTo(index);

            return;
        }

        waiting = window.setTimeout(() => tour.moveTo(index), drawerMs + 40);
    }

    showPanel(stops[0]?.panel ?? false);
    tour.drive(0);

    return { stop: end, isActive: () => !ended };
}
