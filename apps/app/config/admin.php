<?php

declare(strict_types=1);

return [
    /*
    | The addresses that become Super Admin once their email is confirmed. A new address only takes effect when its
    | owner confirms it, so nobody gets the role by signing up with someone else's address.
    */
    'super_admins' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('SUPER_ADMIN_EMAILS', 'noah.gillard@nxt-solutions.com')),
    ))),
];
