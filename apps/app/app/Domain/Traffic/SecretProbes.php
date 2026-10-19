<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * Requests for files that hold keys or passwords on a careless server, such as `.env`, `id_rsa`, or a `.git`
 * folder. Traceway has none of them on the web, so each one is someone looking for a way in.
 */
final class SecretProbes
{
    /**
     * Paths that only someone looking for secrets asks for, as SQL LIKE patterns.
     *
     * @var list<string>
     */
    public const array PATTERNS = [
        '%.env%',
        '%credentials%',
        '%id_rsa%',
        '%.git/%',
        '%tfstate%',
        '%.pem',
        '%.key',
        '%environ%',
    ];

    /** A public file of the planner, which is never a probe however the patterns grow. */
    public const string PUBLIC_FILE = '/build/manifest.json';
}
