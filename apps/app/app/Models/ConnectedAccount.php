<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConnectedAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A rider's account at another service, such as Google or Wahoo. The keys are encrypted with the app key, and
 * only connections that send routes, or must withdraw their access, keep them.
 *
 * @property string $id
 * @property int $user_id
 * @property string $provider
 * @property string|null $provider_user_id
 * @property string|null $account_label
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property list<string>|null $scopes
 * @property bool $needs_reconnect
 * @property Carbon $connected_at
 * @property Carbon|null $last_signed_in_at
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'account_label', 'access_token', 'refresh_token', 'expires_at', 'scopes', 'needs_reconnect', 'connected_at', 'last_signed_in_at', 'last_sent_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class ConnectedAccount extends Model
{
    /** @use HasFactory<ConnectedAccountFactory> */
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
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'needs_reconnect' => 'boolean',
            'connected_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
