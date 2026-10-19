<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Email;
use App\Account\RiderName;
use App\Queries\RowReader;
use App\Routes\SavedLeg;
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
            $this->releaseNotesRead($riderId),
            $this->routeServices($riderId),
            $account->iso('last_seen_at'),
            new RiderRoles()->of($riderId)->value,
        );
    }

    /**
     * The services the rider connected and what went to each, without their tokens.
     *
     * @return list<array{service: string, connected_at: string|null, last_sent_at: string|null, sent: list<array{saved_route_id: string, leg: int, url: string|null, sent_at: string|null}>}>
     */
    private function routeServices(int $riderId): array
    {
        $rows = DB::table('route_service_accounts')
            ->where('user_id', $riderId)
            ->orderBy('service')
            ->get(['service', 'connected_at', 'last_sent_at']);

        $sent = [];

        foreach (DB::table('sent_routes')->where('user_id', $riderId)->oldest('sent_at')->get(['service', 'saved_route_id', 'leg', 'external_url', 'sent_at']) as $row) {
            $reader = new RowReader($row);
            $sent[$reader->string('service')][] = [
                'saved_route_id' => $reader->string('saved_route_id'),
                'leg' => $reader->int('leg'),
                'url' => $reader->optionalString('external_url'),
                'sent_at' => $reader->iso('sent_at'),
            ];
        }

        $services = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $service = $reader->string('service');
            $services[] = [
                'service' => $service,
                'connected_at' => $reader->iso('connected_at'),
                'last_sent_at' => $reader->iso('last_sent_at'),
                'sent' => $sent[$service] ?? [],
            ];
        }

        return $services;
    }

    /**
     * @return list<array{tag: string, read_at: string|null}>
     */
    private function releaseNotesRead(int $riderId): array
    {
        $rows = DB::table('release_reads')
            ->join('releases', 'releases.id', '=', 'release_reads.release_id')
            ->where('release_reads.user_id', $riderId)
            ->oldest('release_reads.read_at')
            ->get(['releases.tag', 'release_reads.read_at']);

        $read = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $read[] = [
                'tag' => $reader->string('tag'),
                'read_at' => $reader->iso('read_at'),
            ];
        }

        return $read;
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
            'last_seen_at',
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
                'layout',
                'legs',
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
                $reader->optionalString('layout'),
                array_map(fn (SavedLeg $savedLeg): array => $savedLeg->toArray(), $reader->legs('legs')),
            );
        }

        return $routes;
    }
}
