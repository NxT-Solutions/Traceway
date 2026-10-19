<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * The names of the AI and search bots that scanners borrow most. A request that gives one of them as its user
 * agent, but that Cloudflare could not verify as that bot, came from someone else.
 */
final class BotNames
{
    /**
     * Each name as it appears in a user agent. A name ending in a dash is a family, such as OAI-SearchBot.
     *
     * @var list<string>
     */
    public const array NAMES = [
        'GPTBot',
        'ClaudeBot',
        'Claude-User',
        'ChatGPT-User',
        'OAI-',
        'PerplexityBot',
        'Perplexity-User',
        'Googlebot',
        'bingbot',
        'Applebot',
        'Amazonbot',
        'CCBot',
        'Bytespider',
        'Meta-',
        'GrokBot',
        'KimiBot',
        'Qwenbot',
        'Hunyuan',
        'YouBot',
        'DuckAssistBot',
    ];

    /**
     * Patterns that match a user agent naming any of the bots. Cloudflare matches them without regard to case.
     *
     * @return list<string>
     */
    public static function likePatterns(): array
    {
        return array_map(static fn (string $name): string => '%'.$name.'%', self::NAMES);
    }

    /**
     * The bot this user agent says it is, or null when it names none of them.
     */
    public static function claimed(string $userAgent): ?string
    {
        $alternatives = array_map(
            static fn (string $name): string => str_ends_with($name, '-') ? preg_quote($name, '/').'[A-Za-z]+' : preg_quote($name, '/'),
            self::NAMES,
        );

        if (preg_match('/'.implode('|', $alternatives).'/i', $userAgent, $matches) !== 1) {
            return null;
        }

        foreach (self::NAMES as $name) {
            if (strcasecmp($name, $matches[0]) === 0) {
                return $name;
            }
        }

        // A family member, such as OAI-SearchBot, keeps the family's spelling and the member's own name.
        $family = substr($matches[0], 0, (int) strpos($matches[0], '-') + 1);

        foreach (self::NAMES as $name) {
            if (strcasecmp($name, $family) === 0) {
                return $name.substr($matches[0], strlen($family));
            }
        }

        return $matches[0];
    }
}
