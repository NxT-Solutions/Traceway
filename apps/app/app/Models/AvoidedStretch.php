<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AvoidedStretchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property float $latitude
 * @property float $longitude
 * @property int|null $way_id
 * @property string|null $name
 * @property list<array{0: float, 1: float}> $shape
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'latitude',
    'longitude',
    'way_id',
    'name',
    'shape',
])]
class AvoidedStretch extends Model
{
    /** @use HasFactory<AvoidedStretchFactory> */
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
            'latitude' => 'float',
            'longitude' => 'float',
            'way_id' => 'integer',
            'shape' => 'array',
        ];
    }
}
