import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Appearance settings" />

            <div className="card">
                <Heading
                    variant="small"
                    title="Appearance"
                    description="Dark is the planner's own look, the map scene from the website. Light keeps the same bones on paper. System follows your device."
                />
                <div className="seg" role="group" aria-label="Theme">
                    {options.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={appearance === option.value}
                            onClick={() => updateAppearance(option.value)}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
                <p className="hint">
                    The map restyles with the theme: ink scene in dark, paper
                    tones in light. The route stays readable in both.
                </p>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [],
};
