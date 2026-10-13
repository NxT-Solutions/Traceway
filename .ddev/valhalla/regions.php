<?php

declare(strict_types=1);

/*
 * Reads Geofabrik's list of map extracts for `ddev valhalla`.
 *
 *   php regions.php list [search]     regions whose id or name matches
 *   php regions.php urls <id> [<id>]  the download link of each region
 *   php regions.php sizes <url>...    the download size of each link
 */

const INDEX = 'https://download.geofabrik.de/index-v1-nogeom.json';
const CACHE = '/tmp/geofabrik-index.json';

/**
 * @return array<string, array{id: string, name: string, parent: string, pbf: string}>
 */
function regions(): array
{
    if (! is_file(CACHE) || filemtime(CACHE) < time() - 86_400) {
        $body = @file_get_contents(INDEX);

        if ($body === false) {
            fwrite(STDERR, "Geofabrik did not answer. Check the network and try again.\n");
            exit(1);
        }

        file_put_contents(CACHE, $body);
    }

    $index = json_decode((string) file_get_contents(CACHE), true);
    $regions = [];

    foreach (is_array($index) ? ($index['features'] ?? []) : [] as $feature) {
        $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
        $pbf = is_array($properties) ? ($properties['urls']['pbf'] ?? null) : null;

        if (! is_array($properties) || ! is_string($pbf) || ! is_string($properties['id'] ?? null)) {
            continue;
        }

        $regions[$properties['id']] = [
            'id' => $properties['id'],
            'name' => is_string($properties['name'] ?? null) ? $properties['name'] : $properties['id'],
            'parent' => is_string($properties['parent'] ?? null) ? $properties['parent'] : '',
            'pbf' => $pbf,
        ];
    }

    return $regions;
}

function size(string $url): string
{
    $headers = @get_headers($url, true);
    $length = is_array($headers) ? ($headers['Content-Length'] ?? null) : null;
    $length = is_array($length) ? end($length) : $length;

    if (! is_numeric($length)) {
        return 'size not known';
    }

    $megabytes = ((int) $length) / 1_048_576;

    return $megabytes >= 1024 ? sprintf('%.1f GB', $megabytes / 1024) : sprintf('%d MB', (int) round($megabytes));
}

$command = $argv[1] ?? 'list';
$arguments = array_slice($argv, 2);

if ($command === 'list') {
    $search = mb_strtolower(trim(implode(' ', $arguments)));
    $found = array_filter(
        regions(),
        fn (array $region): bool => $search === ''
            || str_contains(mb_strtolower($region['id']), $search)
            || str_contains(mb_strtolower($region['name']), $search),
    );
    usort($found, fn (array $a, array $b): int => [$a['parent'], $a['name']] <=> [$b['parent'], $b['name']]);

    if ($found === []) {
        fwrite(STDERR, "No region matches \"{$search}\". Try a shorter word, or `ddev valhalla regions` for all of them.\n");
        exit(1);
    }

    printf("%-34s %-34s %s\n", 'ID', 'NAME', 'PART OF');

    foreach ($found as $region) {
        printf("%-34s %-34s %s\n", $region['id'], $region['name'], $region['parent']);
    }

    exit(0);
}

if ($command === 'urls') {
    $regions = regions();
    $urls = [];

    foreach ($arguments as $id) {
        if (! isset($regions[$id])) {
            fwrite(STDERR, "There is no region \"{$id}\". Find its id with `ddev valhalla regions {$id}`.\n");
            exit(1);
        }

        $urls[] = $regions[$id]['pbf'];
    }

    echo implode(' ', $urls);
    exit(0);
}

if ($command === 'sizes') {
    foreach ($arguments as $url) {
        printf("  %s  %s\n", size($url), $url);
    }

    exit(0);
}

fwrite(STDERR, "Unknown command {$command}.\n");
exit(1);
