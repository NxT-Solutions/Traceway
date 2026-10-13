<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use InvalidArgumentException;
use XMLReader;

/**
 * Reads the line out of a GPX file: its track points, or its route points
 * when it has no track. Several segments join into one line. Heights and
 * times in the file are left behind, since Traceway reads its own.
 */
final class GpxImport
{
    public const int MAX_POINTS = 100_000;

    public static function parse(string $xml): ImportedCourse
    {
        $previous = libxml_use_internal_errors(use_errors: true);
        $xmlReader = XMLReader::fromString($xml, flags: LIBXML_NONET);
        $track = [];
        $route = [];
        $name = null;
        $depth = [];

        try {
            while (@$xmlReader->read()) {
                if ($xmlReader->nodeType === XMLReader::END_ELEMENT) {
                    array_pop($depth);

                    continue;
                }

                if ($xmlReader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                $element = $xmlReader->localName;
                $parent = $depth[count($depth) - 1] ?? null;

                if (! $xmlReader->isEmptyElement) {
                    $depth[] = $element;
                }

                if ($element === 'trkpt' || $element === 'rtept') {
                    $point = self::point($xmlReader);

                    if ($element === 'trkpt') {
                        $track[] = $point;
                    } else {
                        $route[] = $point;
                    }

                    if (count($track) + count($route) > self::MAX_POINTS) {
                        throw new InvalidArgumentException('That file has more points than a route can hold.');
                    }

                    continue;
                }

                if ($element === 'name' && $name === null && in_array($parent, ['metadata', 'trk', 'rte'], strict: true)) {
                    $text = trim($xmlReader->readString());
                    $name = $text === '' ? null : mb_substr($text, 0, 80);
                }
            }

            if (libxml_get_errors() !== []) {
                throw new InvalidArgumentException('That file is not a GPX file Traceway can read.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xmlReader->close();
        }

        $line = self::withoutRepeats($track !== [] ? $track : $route);

        if (count($line) < 2) {
            throw new InvalidArgumentException('That file has no route in it.');
        }

        return new ImportedCourse($name ?? 'Imported route', $line);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function point(XMLReader $xmlReader): array
    {
        $latitude = $xmlReader->getAttribute('lat');
        $longitude = $xmlReader->getAttribute('lon');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw new InvalidArgumentException('A point in that file has no position.');
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('A point in that file is off the map.');
        }

        return [$longitude, $latitude];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    private static function withoutRepeats(array $line): array
    {
        $kept = [];
        $previous = null;

        foreach ($line as $point) {
            if ($point !== $previous) {
                $kept[] = $point;
            }

            $previous = $point;
        }

        return $kept;
    }
}
