<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SavedRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $sport
 * @property string $surface
 * @property string $elevation
 * @property string $roads
 * @property int $distance_metres
 * @property int $ascent_metres
 * @property list<array{0: float, 1: float}> $coordinates
 * @property list<array{0: float, 1: float}>|null $user_coordinates
 * @property list<array{0: float, 1: float}>|null $finish_coordinates
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'sport',
    'surface',
    'elevation',
    'roads',
    'distance_metres',
    'ascent_metres',
    'coordinates',
    'user_coordinates',
    'finish_coordinates',
])]
class SavedRoute extends Model
{
    /** @use HasFactory<SavedRouteFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shareUrl(): string
    {
        return URL::signedRoute('routes.share', $this);
    }

    public function gpxUrl(): string
    {
        return URL::signedRoute('routes.share.gpx', $this);
    }

    public function fitUrl(): string
    {
        return URL::signedRoute('routes.share.fit', $this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_metres' => 'integer',
            'ascent_metres' => 'integer',
            'coordinates' => 'array',
            'user_coordinates' => 'array',
            'finish_coordinates' => 'array',
        ];
    }
}
