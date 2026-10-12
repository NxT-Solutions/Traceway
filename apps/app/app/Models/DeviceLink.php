<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Garmin running the Traceway Connect IQ app. It waits on a pairing code until a rider links it, then holds a hashed token.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $label
 * @property string|null $pairing_code
 * @property string|null $pairing_secret_hash
 * @property Carbon|null $pairing_expires_at
 * @property string|null $token_hash
 * @property Carbon|null $linked_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pairing_code', 'pairing_secret_hash', 'pairing_expires_at'])]
#[Hidden(['pairing_secret_hash', 'token_hash'])]
class DeviceLink extends Model
{
    /** @use HasFactory<DeviceLinkFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
