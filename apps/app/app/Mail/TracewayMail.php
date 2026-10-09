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
        $path = resource_path('views/mail/compiled/'.$template.'.html');

        throw_unless(is_file($path), RuntimeException::class, 'Missing Traceway mail template ['.$template.'].');

        $html = file_get_contents($path);

        throw_if($html === false, RuntimeException::class, 'Could not read Traceway mail template ['.$template.'].');

        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['%%'.strtoupper($key).'%%'] = e($value);
        }

        return strtr($html, $replacements);
    }
}
