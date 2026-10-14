<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

final class TracewayMail
{
    /**
     * @param  array<string, string>  $values
     */
    public static function html(string $template, array $values): string
    {
        $replacements = [];

        foreach ([...self::footer(), ...$values] as $key => $value) {
            $replacements['%%'.strtoupper($key).'%%'] = e($value);
        }

        return strtr(self::compiled($template), $replacements);
    }

    /**
     * The plain-text part of the same message. A link keeps its address, so
     * a reader without HTML can still confirm, reset, or open the planner.
     *
     * @param  array<string, string>  $values
     */
    public static function text(string $template, array $values): string
    {
        $html = self::compiled($template);
        $start = strpos($html, '<body');
        $body = $start === false ? $html : substr($html, $start);

        $patterns = [
            '/<div[^>]*display:\s*none[^>]*>.*?<\/div>/is' => '',
            '/<!--.*?-->/s' => '',
            '/<a [^>]*class="tw-brand"[^>]*>.*?<\/a>/is' => 'Traceway',
            '/<br\s*\/?>/i' => "\n",
            '/<\/(p|h1|tr|table)>/i' => "\n\n",
        ];
        $text = (string) preg_replace(array_keys($patterns), array_values($patterns), $body);
        $text = (string) preg_replace_callback(
            '/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is',
            fn (array $link): string => trim(strip_tags($link[2])) === $link[1] ? $link[1] : trim(strip_tags($link[2])).': '.$link[1],
            $text,
        );
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $replacements = [];

        foreach ([...self::footer(), ...$values] as $key => $value) {
            $replacements['%%'.strtoupper($key).'%%'] = $value;
        }

        $text = strtr($text, $replacements);
        $lines = array_map(
            fn (string $line): string => (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $line),
            explode("\n", $text),
        );
        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim($text)."\n";
    }

    /**
     * Every message ends with links to the privacy policy and the terms.
     *
     * @return array<string, string>
     */
    private static function footer(): array
    {
        return [
            'privacy' => url('/privacy'),
            'terms' => url('/terms'),
        ];
    }

    private static function compiled(string $template): string
    {
        $path = resource_path('views/mail/compiled/'.$template.'.html');

        throw_unless(is_file($path), RuntimeException::class, 'Missing Traceway mail template ['.$template.'].');

        $html = file_get_contents($path);

        throw_if($html === false, RuntimeException::class, 'Could not read Traceway mail template ['.$template.'].');

        return $html;
    }
}
