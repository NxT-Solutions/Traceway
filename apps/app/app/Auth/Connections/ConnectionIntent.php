<?php

declare(strict_types=1);

namespace App\Auth\Connections;

/**
 * Why the rider went to a service's sign-in page, so its answer is used for that and nothing else.
 */
enum ConnectionIntent: string
{
    /** A guest logs in, or opens an account. */
    case SignIn = 'sign_in';

    /** A signed-in rider adds the account from Settings. */
    case Connect = 'connect';

    /** A signed-in rider without a password proves it is them before a sensitive step. */
    case Confirm = 'confirm';
}
