<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Queries\Routes\SharedRoute;

/**
 * What a chat or a post shows for a shared link: a title, a line about the
 * route, and the picture of it on the map. The link is private, so search
 * engines are asked to leave it out.
 */
final readonly class SharePreview
{
    private const array RIDES = [
        'road' => 'road ride',
        'gravel' => 'gravel ride',
        'mtb' => 'mountain bike ride',
        'run' => 'run',
        'trail_run' => 'trail run',
        'walk' => 'walk',
        'hike' => 'hike',
        'swim' => 'swim',
    ];

    private const array VERBS = [
        'road' => 'ride',
        'gravel' => 'ride',
        'mtb' => 'ride',
        'run' => 'run',
        'trail_run' => 'run',
        'walk' => 'walk',
        'hike' => 'hike',
        'swim' => 'swim',
    ];

    private const array SURFACES = [
        'paved' => 'paved',
        'gravel' => 'gravel',
        'dirt' => 'dirt and trail',
    ];

    public function __construct(
        public string $title,
        public string $description,
        public string $imageUrl,
        public string $imageAlt,
        public string $url,
    ) {}

    public static function of(SharedRoute $sharedRoute, string $url): self
    {
        $name = trim($sharedRoute->name) === '' ? 'A route' : trim($sharedRoute->name);
        $distance = self::kilometres($sharedRoute->distanceMetres);

        if ($sharedRoute->legs !== []) {
            $parts = array_map(
                fn (array $leg): string => (self::VERBS[$leg['discipline']] ?? $leg['discipline']).' '.self::kilometres($leg['distance_metres']),
                $sharedRoute->legs,
            );
            $legs = ucfirst(implode(', then ', $parts));

            return new self(
                title: "{$name} · {$distance}",
                description: "{$legs}. Open it on the map, or send each leg to your watch.",
                imageUrl: $sharedRoute->imageUrl,
                imageAlt: "Map of {$name}: {$legs}.",
                url: $url,
            );
        }

        $ride = self::RIDES[$sharedRoute->sport] ?? 'route';
        $climb = $sharedRoute->ascentMetres > 0 ? " with {$sharedRoute->ascentMetres} m of climbing" : '';
        $surface = self::surface($sharedRoute);
        $stops = count(is_array($sharedRoute->details['stops'] ?? null) ? $sharedRoute->details['stops'] : []);
        $water = $stops > 0 ? ' Water, toilets, or coffee on the way.' : '';

        return new self(
            title: "{$name} · {$distance} {$ride}",
            description: ucfirst("{$distance} {$ride}{$climb}, mostly {$surface}.{$water} Open it on the map, or send it to your watch or bike computer."),
            imageUrl: $sharedRoute->imageUrl,
            imageAlt: "Map of {$name}, a {$distance} {$ride}.",
            url: $url,
        );
    }

    /**
     * @return array{title: string, description: string, image: string, image_alt: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl,
            'image_alt' => $this->imageAlt,
            'url' => $this->url,
        ];
    }

    private static function kilometres(int $metres): string
    {
        return number_format($metres / 1000, 1).' km';
    }

    /**
     * The surface the route mostly runs on, read from the road when the
     * route has its details, or else the surface the rider asked for.
     */
    private static function surface(SharedRoute $sharedRoute): string
    {
        $shares = is_array($sharedRoute->details['surfaces'] ?? null) ? $sharedRoute->details['surfaces'] : [];
        $most = null;
        $mostMetres = 0;

        foreach ($shares as $share) {
            if (is_array($share) && is_string($share['surface'] ?? null) && is_int($share['metres'] ?? null) && $share['metres'] > $mostMetres && $share['surface'] !== 'unknown') {
                $most = $share['surface'];
                $mostMetres = $share['metres'];
            }
        }

        return self::SURFACES[$most ?? $sharedRoute->surface] ?? 'mixed roads and paths';
    }
}
