<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RouteServiceAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A rider's account at a service Traceway sends routes to. The tokens are encrypted with the app key.
 *
 * @property string $id
 * @property int $user_id
 * @property string $service
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property bool $needs_reconnect
 * @property Carbon $connected_at
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'service', 'access_token', 'refresh_token', 'expires_at', 'needs_reconnect', 'connected_at', 'last_sent_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class RouteServiceAccount extends Model
{
    /** @use HasFactory<RouteServiceAccountFactory> */
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
            'needs_reconnect' => 'boolean',
            'connected_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
