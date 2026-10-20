<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\ConnectionGroup;
use App\Account\ConnectionProvider;
use App\Auth\Connections\LastSignIn;
use App\Contracts\Auth\IdentityProviders;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;

/**
 * The services the log in page offers. The four riders use most come first, with the one this browser last used
 * at the front; the rest wait behind "More ways to log in".
 */
final readonly class SignInOptionsQuery
{
    public const int FEATURED = 4;

    public function __construct(
        private IdentityProviders $identityProviders,
        private Repository $repository,
    ) {}

    /**
     * @return array{featured: list<array{slug: string, label: string}>, more: int, lastUsed: string|null}
     */
    public function forLogin(?string $lastUsed): array
    {
        $ranked = $this->ranked();
        $featured = array_slice($ranked, 0, self::FEATURED);
        $last = $lastUsed === null ? null : ConnectionProvider::tryFrom($lastUsed);

        if ($last instanceof ConnectionProvider && in_array($last, $ranked, strict: true)) {
            $featured = array_slice([$last, ...array_values(array_filter($featured, static fn (ConnectionProvider $connectionProvider): bool => $connectionProvider !== $last))], 0, self::FEATURED);
        }

        return [
            'featured' => array_map($this->option(...), $featured),
            'more' => count($ranked) - count($featured),
            'lastUsed' => $lastUsed !== null && LastSignIn::known($lastUsed) ? $lastUsed : null,
        ];
    }

    /**
     * Every service set up here, in its group, for the "More ways to log in" page.
     *
     * @return array{groups: list<array{key: string, label: string, providers: list<array{slug: string, label: string}>}>, lastUsed: string|null}
     */
    public function all(?string $lastUsed): array
    {
        $ranked = $this->ranked();
        $groups = [];

        foreach (ConnectionGroup::cases() as $connectionGroup) {
            $providers = array_values(array_filter($ranked, static fn (ConnectionProvider $connectionProvider): bool => $connectionProvider->group() === $connectionGroup));

            if ($providers !== []) {
                $groups[] = ['key' => $connectionGroup->value, 'label' => $connectionGroup->label(), 'providers' => array_map($this->option(...), $providers)];
            }
        }

        return [
            'groups' => $groups,
            'lastUsed' => $lastUsed !== null && LastSignIn::known($lastUsed) ? $lastUsed : null,
        ];
    }

    /**
     * The services set up here, the most connected first. Riders' counts are kept for an hour, so the page does
     * not count on every visit. Ties keep the starting order.
     *
     * @return list<ConnectionProvider>
     */
    private function ranked(): array
    {
        $enabled = $this->identityProviders->enabled();

        if ($enabled === []) {
            return [];
        }

        // Only connections that can log a rider in count: Wahoo and Ride with GPS connections from before have no
        // number from the service until connections:identify finds it.
        $counts = $this->repository->remember('connections:popularity', 3600, static fn (): array => DB::table('connected_accounts')
            ->whereNotNull('provider_user_id')
            ->selectRaw('provider, count(*) as riders')
            ->groupBy('provider')
            ->pluck('riders', 'provider')
            ->map(static fn (mixed $riders): int => is_numeric($riders) ? (int) $riders : 0)
            ->all());

        $count = static fn (ConnectionProvider $connectionProvider): int => $counts[$connectionProvider->value] ?? 0;

        usort($enabled, static fn (ConnectionProvider $a, ConnectionProvider $b): int => [$count($b), $a->rank()] <=> [$count($a), $b->rank()]);

        return $enabled;
    }

    /**
     * @return array{slug: string, label: string}
     */
    private function option(ConnectionProvider $connectionProvider): array
    {
        return ['slug' => $connectionProvider->value, 'label' => $connectionProvider->label()];
    }
}
