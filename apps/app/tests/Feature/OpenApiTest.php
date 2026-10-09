<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('publishes one spec for the signed-in person and a signed share', function (): void {
    Artisan::call('l5-swagger:generate', ['documentation' => 'traceway']);

    $document = spec('api-docs.json');
    $paths = array_keys($document['paths']);
    $account = $document['components']['schemas']['PersonalDataDocument']['properties']['account']['properties'];
    $preview = $document['paths']['/routes/preview']['post'];

    expect($paths)->toContain('/up', '/share/{savedRoute}/gpx', '/routes/preview', '/settings/privacy/export')
        ->and(array_keys($account))->not->toContain('password', 'two_factor_secret', 'remember_token')
        ->and($document['info']['description'])->toContain('no list of other people')
        ->and($document['paths']['/up']['get']['security'])->toBe([])
        ->and($preview['security'])->toBe([['session' => []]])
        ->and($preview['requestBody']['$ref'])->toBe('#/components/requestBodies/PreviewRouteRequest')
        ->and($preview['responses']['200']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/RoutePreview')
        ->and($preview['responses']['422']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/Error')
        ->and($document['components']['schemas'])->not->toHaveKey('PreviewRouteRequest')
        ->and($document['components']['schemas']['Error']['properties'])->toHaveKeys(['message', 'errors'])
        ->and($document['components']['schemas']['Error']['properties'])->not->toHaveKey('exception');
});

it('draws the reference at one address', function (): void {
    $this->get('/docs')->assertOk()->assertSee('api-reference', false)->assertDontSee('swagger-ui', false);
    $this->get('/docs/public')->assertRedirect('/docs');
    $this->get('/docs/account')->assertRedirect('/docs');
});

/**
 * @return array<string, mixed>
 */
function spec(string $file): array
{
    $document = json_decode((string) file_get_contents(storage_path('api-docs/'.$file)), associative: true);

    expect($document)->toBeArray();

    /** @var array<string, mixed> $document */
    return $document;
}
