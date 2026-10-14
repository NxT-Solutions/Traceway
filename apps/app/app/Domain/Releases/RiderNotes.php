<?php

declare(strict_types=1);

namespace App\Domain\Releases;

/**
 * Cuts the notes GitHub writes for a release down to what a rider reads: what is new,
 * what was fixed, and what changed on a device. Docs, dependencies, and who opened which
 * pull request are for the people building Traceway. Notes written by hand, with none of
 * GitHub's groups, are kept whole.
 */
final class RiderNotes
{
    /**
     * The groups in .github/release.yml a rider reads.
     */
    private const array KEPT = ['new', 'fixes', 'devices'];

    /**
     * The groups GitHub writes that are not for a rider.
     */
    private const array DROPPED = ['docs', 'dependencies', 'other changes', 'new contributors', "what's changed", 'what’s changed'];

    private const string COMMIT_TYPES = 'feat|fix|perf|refactor|chore|docs|test|ci|build|style|revert|content';

    public static function from(string $body): string
    {
        $lines = self::lines((string) preg_replace('/<!--.*?-->/su', '', $body));
        $grouped = self::isGrouped($lines);
        $kept = [];
        $keeping = ! $grouped;

        foreach ($lines as $line) {
            $title = self::headingTitle($line);

            if ($title !== null && $grouped) {
                $keeping = in_array(self::groupKey($title), self::KEPT, strict: true);

                if ($keeping) {
                    $kept[] = '';
                    $kept[] = '## '.$title;
                    $kept[] = '';
                }

                continue;
            }

            if (! $keeping || preg_match('/^\s*\*\*Full Changelog\*\*/iu', $line) === 1) {
                continue;
            }

            $kept[] = self::cleanLine($line);
        }

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)));
    }

    /**
     * The first change in the notes as one line of plain text, for a short list.
     */
    public static function headline(string $notes): ?string
    {
        foreach (self::lines($notes) as $line) {
            if (preg_match('/^\s*[*-]\s+(.+)$/u', $line, $match) === 1) {
                $text = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $match[1]);
                $text = trim(str_replace(['**', '__', '`'], '', $text));

                return $text === '' ? null : $text;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $lines = preg_split('/\R/u', $text);

        return $lines === false ? [] : $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function isGrouped(array $lines): bool
    {
        foreach ($lines as $line) {
            $title = self::headingTitle($line);

            if ($title !== null && in_array(self::groupKey($title), [...self::KEPT, ...self::DROPPED], strict: true)) {
                return true;
            }
        }

        return false;
    }

    private static function headingTitle(string $line): ?string
    {
        if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    private static function groupKey(string $title): string
    {
        return mb_strtolower(trim((string) preg_replace('/^[^\p{L}]+/u', '', $title)));
    }

    /**
     * A change reads as a plain sentence: no "by @someone in" link, no pull request number,
     * and no gitmoji or "feat(scope):" in front.
     */
    private static function cleanLine(string $line): string
    {
        if (preg_match('/^(\s*[*-]\s+)(.*)$/u', $line, $match) !== 1) {
            return rtrim($line);
        }

        $text = (string) preg_replace('/\s+by\s+@[\w.-]+(?:\[bot\])?\s+in\s+https?:\/\/\S+$/u', '', $match[2]);
        $text = (string) preg_replace('/\s+in\s+https?:\/\/github\.com\/\S+\/pull\/\d+$/u', '', $text);
        $text = (string) preg_replace('/\s*\(#\d+\)$/u', '', $text);
        $text = (string) preg_replace('/^(?:(?::[a-z0-9_+-]+:|[\p{So}\p{Sk}\p{Mn}\p{Cf}\x{200D}])+\s*)/iu', '', $text);
        $text = (string) preg_replace('/^(?:'.self::COMMIT_TYPES.')(?:\([^)]*\))?!?:\s*/iu', '', $text);
        $text = trim($text);

        return $match[1].mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
