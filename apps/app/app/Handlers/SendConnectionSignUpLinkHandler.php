<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\SendConnectionSignUpLinkCommand;
use App\Contracts\Mail\AccountMailer;

final readonly class SendConnectionSignUpLinkHandler
{
    public function __construct(private AccountMailer $accountMailer) {}

    public function handle(SendConnectionSignUpLinkCommand $sendConnectionSignUpLinkCommand): void
    {
        $this->accountMailer->connectionSignUp(
            $sendConnectionSignUpLinkCommand->name,
            $sendConnectionSignUpLinkCommand->email,
            $sendConnectionSignUpLinkCommand->connectionProvider,
            $sendConnectionSignUpLinkCommand->token,
        );
    }
}
