<?php

declare(strict_types=1);

namespace App\Queries\Account;

final readonly class SecurityPage
{
    /**
     * @param  list<array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>  $passkeys
     * @param  bool  $passkeyIsLastWayIn  true when the rider has no password, one passkey, and no connection that logs them in here
     */
    public function __construct(
        public bool $canManageTwoFactor,
        public bool $canManagePasskeys,
        public array $passkeys,
        public string $passwordRules,
        public bool $hasPassword,
        public bool $passkeyIsLastWayIn,
        public ?bool $twoFactorEnabled,
        public ?bool $requiresConfirmation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $page = [
            'canManageTwoFactor' => $this->canManageTwoFactor,
            'canManagePasskeys' => $this->canManagePasskeys,
            'passkeys' => $this->passkeys,
            'passwordRules' => $this->passwordRules,
            'hasPassword' => $this->hasPassword,
            'passkeyIsLastWayIn' => $this->passkeyIsLastWayIn,
        ];

        if ($this->twoFactorEnabled !== null && $this->requiresConfirmation !== null) {
            $page['twoFactorEnabled'] = $this->twoFactorEnabled;
            $page['requiresConfirmation'] = $this->requiresConfirmation;
        }

        return $page;
    }
}
