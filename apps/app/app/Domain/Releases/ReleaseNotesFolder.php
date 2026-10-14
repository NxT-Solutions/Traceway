<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The rider notes of one version, as kept in resources/releases/<version>/.
 *
 * _release.md names the release (title, summary, an optional date) and may hold notes
 * written for the whole release. Every other file is the notes of one branch: its
 * section (New, Fixes, Devices, or a heading of its own) and the changes as a list.
 * The notes riders read are the release's own words, then each section with the
 * changes of every branch in it.
 */
final class ReleaseNotesFolder
{
    public const string TAG = '/^v\d+\.\d+\.\d+$/';

    public const string RELEASE_FILE = '_release.md';

    /**
     * The sections riders know, in the order they read them. Any other section follows.
     */
    private const array SECTIONS = ['New', 'Fixes', 'Devices'];

    /**
     * @param  array<string, string>  $branchNotes  The contents of each branch's file, by file name.
     */
    public static function read(string $tag, string $releaseFile, array $branchNotes): Release
    {
        [$fields, $body] = self::split($releaseFile, $tag.'/'.self::RELEASE_FILE);

        $title = $fields['title'] ?? '';
        throw_if($title === '', InvalidArgumentException::class, $tag.'/'.self::RELEASE_FILE.' needs a title.');

        $sections = self::sections($tag, $branchNotes);
        $parts = trim($body) === '' ? [] : [trim($body)];

        foreach ($sections as $section => $changes) {
            $parts[] = '## '.$section."\n\n".implode("\n", $changes);
        }

        throw_if($parts === [], InvalidArgumentException::class, $tag.' has no notes for riders.');

        return new Release(
            $tag,
            $title,
            ($fields['summary'] ?? '') === '' ? null : $fields['summary'],
            implode("\n\n", $parts),
            isset($fields['date']) ? self::date($fields['date'], $tag) : null,
        );
    }

    /**
     * Checks one branch's notes before they are released.
     *
     * @return array{0: string, 1: string} The section, and the changes.
     */
    public static function branchNote(string $name, string $contents): array
    {
        [$fields, $body] = self::split($contents, $name);

        $section = $fields['section'] ?? '';
        throw_if($section === '', InvalidArgumentException::class, $name.' needs a section, such as New, Fixes, or Devices.');
        throw_if(trim($body) === '', InvalidArgumentException::class, $name.' has no changes under its section.');

        $known = array_values(array_filter(self::SECTIONS, fn (string $known): bool => strcasecmp($known, $section) === 0));

        return [$known[0] ?? $section, trim($body)];
    }

    /**
     * The _release.md a release starts with.
     */
    public static function releaseFile(string $title, ?string $summary): string
    {
        throw_if(trim($title) === '', InvalidArgumentException::class, 'A release needs a title for riders.');

        $lines = ['---', 'title: '.self::oneLine($title)];

        if ($summary !== null && trim($summary) !== '') {
            $lines[] = 'summary: '.self::oneLine($summary);
        }

        $lines[] = '---';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string>  $branchNotes
     * @return array<string, list<string>>
     */
    private static function sections(string $tag, array $branchNotes): array
    {
        ksort($branchNotes);
        $sections = array_fill_keys(self::SECTIONS, []);

        foreach ($branchNotes as $name => $contents) {
            [$section, $changes] = self::branchNote($tag.'/'.$name, $contents);
            $sections[$section][] = $changes;
        }

        return array_filter($sections, fn (array $changes): bool => $changes !== []);
    }

    /**
     * Front matter between two --- lines, as plain "key: value" pairs, and the rest.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private static function split(string $contents, string $name): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", ltrim($contents, "\u{FEFF}"));

        if (preg_match('/^---\n(?:(.*?)\n)?---(?:\n|$)(.*)$/s', $contents, $match) !== 1) {
            throw new InvalidArgumentException($name.' starts with front matter between two --- lines.');
        }

        $fields = [];

        foreach (explode("\n", $match[1]) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^([a-z]+):\s*(.*)$/', $line, $field) !== 1) {
                throw new InvalidArgumentException($name.' has a front matter line that is not "key: value": '.$line);
            }

            $fields[$field[1]] = self::unquote(trim($field[2]));
        }

        return [$fields, $match[2]];
    }

    private static function unquote(string $value): string
    {
        if (preg_match('/^(["\'])(.*)\1$/s', $value, $match) === 1) {
            return $match[2];
        }

        return $value;
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function date(string $value, string $tag): DateTimeImmutable
    {
        $dateTimeZone = new DateTimeZone('UTC');

        foreach (['!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s\Z', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $dateTimeZone);

            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        throw new InvalidArgumentException($tag.'/'.self::RELEASE_FILE.' has a date that is not 2026-10-14 or 2026-10-14T21:42:41Z: '.$value);
    }
}
