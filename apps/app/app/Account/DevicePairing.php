<?php

declare(strict_types=1);

namespace App\Account;

/**
 * A pairing the device just opened. The code is for the rider to type. The secret stays on the device.
 */
final readonly class DevicePairing
{
    public const int SECONDS = 600;

    public function __construct(
        public PairingCode $code,
        public string $secret,
    ) {}

    /**
     * @return array{code: string, secret: string, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'secret' => $this->secret,
            'expires_in' => self::SECONDS,
        ];
    }
}
