<?php

declare(strict_types=1);

namespace App\Account;

/**
 * Why connecting an account, or logging in with one, did not work. Each says what happened and what to do, in
 * the words the rider sees.
 */
enum ConnectionProblem: string
{
    /** The rider said no at the service, or closed its page. */
    case Cancelled = 'cancelled';

    /** The answer did not belong to a sign-in started here, or it came too late. */
    case Expired = 'expired';

    /** The service turned the answer down. */
    case Refused = 'refused';

    /** The service could not be reached. */
    case Unavailable = 'unavailable';

    /** The service did not say who the rider is. */
    case Incomplete = 'incomplete';

    /** The account at the service is already connected to another Traceway account. */
    case TakenByAnotherRider = 'taken';

    /** Disconnecting would leave the rider no way to log in. */
    case LastSignInMethod = 'last_method';

    /** The account at the service is not the one connected to this Traceway account. */
    case NotLinked = 'not_linked';

    /** Traceway does not open new accounts right now. */
    case SignUpClosed = 'sign_up_closed';

    /** The email already belongs to a Traceway account, which the rider has to log in to first. */
    case EmailTaken = 'email_taken';

    public function message(ConnectionProvider $connectionProvider): string
    {
        $service = $connectionProvider->label();

        return match ($this) {
            self::Cancelled => $service.' did not connect, because it was cancelled there.',
            self::Expired => 'That took too long, or it started in another tab. Try again.',
            self::Refused => $service.' turned the request down. Try again.',
            self::Unavailable => $service.' could not be reached. Try again in a minute.',
            self::Incomplete => $service.' did not say who you are. Try again, or use another way to log in.',
            self::TakenByAnotherRider => 'This '.$service.' account is already connected to another Traceway account.',
            self::LastSignInMethod => 'Add a password or a passkey first, so you can still log in.',
            self::NotLinked => 'That '.$service.' account is not the one connected to your Traceway account.',
            self::SignUpClosed => 'Traceway is not opening new accounts right now.',
            self::EmailTaken => 'That email already has a Traceway account. Log in to it, then connect '.$service.' in Settings.',
        };
    }
}
