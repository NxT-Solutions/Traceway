<?php

declare(strict_types=1);

namespace App\Domain\Releases;

/**
 * Reads the notes of a release written for riders.
 */
final class RiderNotes
{
    /**
     * One line of plain text for a short list, when a release has no summary: the words
     * written above the changes when there are some, otherwise the first change.
     */
    public static function headline(string $notes): ?string
    {
        $lines = preg_split('/\R/u', $notes);
        $lines = $lines === false ? [] : $lines;

        $firstChange = null;

        foreach ($lines as $line) {
            if (trim($line) === '' || preg_match('/^\s{0,3}#/u', $line) === 1) {
                continue;
            }

            if (preg_match('/^\s*[*-]\s+(.+)$/u', $line, $match) === 1) {
                $firstChange = $match[1];

                break;
            }

            return self::plain($line);
        }

        return $firstChange === null ? null : self::plain($firstChange);
    }

    /**
     * Markdown as words: links become their text, and emphasis and code marks go.
     */
    private static function plain(string $text): ?string
    {
        $text = (string) preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
        $text = trim(str_replace(['**', '__', '`'], '', $text));

        return $text === '' ? null : $text;
    }
}
