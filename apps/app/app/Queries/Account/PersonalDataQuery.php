<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Email;
use App\Account\RiderName;
use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * The rider's export and the Your data page. Password, two-factor secrets,
 * recovery codes, and the remember token are never selected.
 */
final class PersonalDataQuery
{
    public function page(int $riderId): PrivacyPage
    {
        $account = $this->account($riderId);

        return new PrivacyPage(
            new RiderName($account->string('name')),
            new Email($account->string('email')),
            $account->iso('email_verified_at'),
            $account->optionalString('two_factor_confirmed_at') !== null,
            $account->iso('created_at'),
            $this->summaries($riderId),
        );
    }

    public function document(int $riderId): PersonalDataDocument
    {
        $account = $this->account($riderId);

        return new PersonalDataDocument(
            now()->toIso8601String(),
            new RiderName($account->string('name')),
            new Email($account->string('email')),
            $account->iso('email_verified_at'),
            $account->optionalString('two_factor_confirmed_at') !== null,
            $account->iso('created_at'),
            $account->iso('updated_at'),
            $this->routes($riderId),
        );
    }

    private function account(int $riderId): RowReader
    {
        $row = DB::table('users')->where('id', $riderId)->first([
            'name',
            'email',
            'email_verified_at',
            'two_factor_confirmed_at',
            'created_at',
            'updated_at',
        ]);

        abort_if($row === null, 404);

        return new RowReader($row);
    }

    /**
     * @return list<SavedRouteSummary>
     */
    private function summaries(int $riderId): array
    {
        $rows = DB::table('saved_routes')
            ->where('user_id', $riderId)->latest()
            ->orderByDesc('id')
            ->get(['name', 'sport', 'distance_metres', 'created_at']);

        $routes = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $routes[] = new SavedRouteSummary(
                $reader->string('name'),
                $reader->string('sport'),
                $reader->int('distance_metres'),
                $reader->iso('created_at'),
            );
        }

        return $routes;
    }

    /**
     * @return list<ExportedRoute>
     */
    private function routes(int $riderId): array
    {
        $rows = DB::table('saved_routes')
            ->where('user_id', $riderId)->oldest()
            ->orderBy('id')
            ->get([
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
                'created_at',
                'updated_at',
            ]);

        $routes = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $routes[] = new ExportedRoute(
                $reader->string('name'),
                $reader->string('sport'),
                $reader->string('surface'),
                $reader->string('elevation'),
                $reader->string('roads'),
                $reader->int('distance_metres'),
                $reader->int('ascent_metres'),
                $reader->line('coordinates'),
                $reader->optionalLine('user_coordinates'),
                $reader->optionalLine('finish_coordinates'),
                $reader->iso('created_at'),
                $reader->iso('updated_at'),
            );
        }

        return $routes;
    }
}
