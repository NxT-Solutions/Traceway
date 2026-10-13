<?php

declare(strict_types=1);

use App\Domain\Courses\GpxImport;
use App\Domain\Courses\ImportedCourse;

it('reads the track and its name', function (): void {
    $importedCourse = GpxImport::parse(<<<'GPX_WRAP'
    <?xml version="1.0" encoding="UTF-8"?>
    <gpx version="1.1" creator="Somewhere" xmlns="http://www.topografix.com/GPX/1/1">
      <metadata><name>Sunday &amp; coffee</name></metadata>
      <trk><name>Track name</name>
        <trkseg>
          <trkpt lat="51.1" lon="4.9"><ele>12</ele><time>2026-01-01T08:00:00Z</time></trkpt>
          <trkpt lat="51.1" lon="4.9"></trkpt>
          <trkpt lat="51.11" lon="4.91"/>
        </trkseg>
        <trkseg>
          <trkpt lat="51.12" lon="4.92"/>
        </trkseg>
      </trk>
    </gpx>
    GPX_WRAP);

    expect($importedCourse->name)->toBe('Sunday & coffee')
        ->and($importedCourse->coordinates)->toBe([[4.9, 51.1], [4.91, 51.11], [4.92, 51.12]]);
});

it('reads route points when the file has no track', function (): void {
    $importedCourse = GpxImport::parse('<gpx><rte><name>Planned</name><rtept lat="51.1" lon="4.9"/><rtept lat="51.2" lon="5.0"/></rte></gpx>');

    expect($importedCourse->name)->toBe('Planned')
        ->and($importedCourse->coordinates)->toBe([[4.9, 51.1], [5.0, 51.2]]);
});

it('names a file without a name', function (): void {
    expect(GpxImport::parse('<gpx><trk><trkseg><trkpt lat="1" lon="2"/><trkpt lat="1.1" lon="2"/></trkseg></trk></gpx>')->name)
        ->toBe('Imported route');
});

it('refuses a file it cannot use', function (string $xml): void {
    expect(fn (): ImportedCourse => GpxImport::parse($xml))->toThrow(InvalidArgumentException::class);
})->with([
    'not xml' => ['this is a photo'],
    'broken xml' => ['<gpx><trk><trkseg><trkpt lat="1" lon="2"></trkseg>'],
    'no points' => ['<gpx><trk><name>Empty</name></trk></gpx>'],
    'one point' => ['<gpx><trk><trkseg><trkpt lat="1" lon="2"/></trkseg></trk></gpx>'],
    'no position' => ['<gpx><trk><trkseg><trkpt lat="1"/><trkpt lat="1" lon="2"/></trkseg></trk></gpx>'],
    'off the map' => ['<gpx><trk><trkseg><trkpt lat="95" lon="2"/><trkpt lat="1" lon="2"/></trkseg></trk></gpx>'],
]);

it('does not expand entities or read files the GPX points to', function (): void {
    $xml = '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY secret SYSTEM "file:///etc/passwd">]>'
        .'<gpx><trk><name>&secret;</name><trkseg><trkpt lat="1" lon="2"/><trkpt lat="1.1" lon="2"/></trkseg></trk></gpx>';

    try {
        $name = GpxImport::parse($xml)->name;
    } catch (InvalidArgumentException) {
        $name = null;
    }

    expect($name ?? '')->not->toContain('root:');
});
