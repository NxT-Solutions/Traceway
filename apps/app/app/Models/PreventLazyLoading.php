<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PreventLazyLoading
{
    public static function outsideProduction(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
