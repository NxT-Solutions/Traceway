<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

/**
 * @return array<string, list<string>>
 */
function policyOf(TestResponse|Response $response): array
{
    $header = $response->headers->get('Content-Security-Policy');
    expect($header)->toBeString();

    $directives = [];

    foreach (explode(';', (string) $header) as $directive) {
        $parts = preg_split('/\s+/', trim($directive)) ?: [];
        $directives[(string) array_shift($parts)] = $parts;
    }

    return $directives;
}

/**
 * The page where a rider approves a chat app, as Claude or ChatGPT opens it.
 */
function chatAppApproval(): TestResponse
{
    // Passport signs with a key pair. The test makes its own.
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    throw_unless($key instanceof OpenSSLAsymmetricKey && openssl_pkey_export($key, $private));
    config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key'] ?? null]);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], confidential: false);

    return test()->actingAs(User::factory()->create())->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => '',
        'state' => 'state',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]))->assertOk();
}

function nginxConfig(): string
{
    return file_get_contents(dirname(base_path(), 2).'/.docker/production/images/configs/nginx/nginx.conf') ?: '';
}

it('runs only the scripts Traceway wrote on a planner page', function (): void {
    $response = $this->get('/login')->assertOk();
    $policy = policyOf($response);

    preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", implode(' ', $policy['script-src']), $nonce);
    expect($nonce)->toHaveKey(1);

    $html = (string) $response->getContent();
    preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts);

    expect($policy['default-src'])->toBe(["'none'"])
        ->and($policy['script-src'])->not->toContain("'unsafe-inline'")
        ->and($policy['script-src'])->not->toContain("'unsafe-eval'")
        ->and($policy['base-uri'])->toBe(["'none'"])
        ->and($policy['form-action'])->toBe(["'self'"])
        ->and($policy['frame-ancestors'])->toBe(["'none'"])
        ->and($scripts[0])->not->toBeEmpty()
        ->and($scripts[0])->each->toContain('nonce="'.$nonce[1].'"');
});

it('gives every page a new nonce', function (): void {
    $first = policyOf($this->get('/login'))['script-src'];
    $second = policyOf($this->get('/login'))['script-src'];

    expect($first)->not->toBe($second);
});

it('lets the browser reach only the hosts the privacy policy names', function (): void {
    $policy = policyOf($this->get('/register'));
    $hosts = collect($policy)
        ->except(['frame-ancestors'])
        ->flatten()
        ->filter(fn (string $source): bool => str_starts_with($source, 'https://'))
        ->map(fn (string $source): string => (string) parse_url($source, PHP_URL_HOST))
        ->unique()
        ->sort()
        ->values()
        ->all();

    // The same hosts GdprTest allows in the planner's own code.
    expect($hosts)->toBe(['challenges.cloudflare.com', 'tiles.openfreemap.org']);
})->group('gdpr');

it('keeps the planner out of other sites and its own windows', function (): void {
    $response = $this->get('/login');

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=63072000; includeSubDomains; preload')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Cross-Origin-Resource-Policy'))->toBe('same-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()', 'microphone=()', 'geolocation=(self)');
});

it('cuts a page off from the window that opened it, except on the way to sign in', function (string $path, ?string $opener): void {
    expect($this->get($path)->headers->get('Cross-Origin-Opener-Policy'))->toBe($opener);
})->with([
    'status' => ['/status', 'same-origin'],
    'dashboard' => ['/dashboard', 'same-origin'],
    'sign in' => ['/login', null],
    'sign up' => ['/register', null],
]);

it('keeps the chat app sign-in window linked to the app that opened it', function (): void {
    expect(chatAppApproval()->headers->has('Cross-Origin-Opener-Policy'))->toBeFalse();
});

it('lets an approved chat app send the browser back to itself', function (): void {
    $testResponse = chatAppApproval();
    $policy = policyOf($testResponse);

    expect($policy)->not->toHaveKey('form-action')
        ->and($policy['script-src'])->not->toContain("'unsafe-inline'")
        ->and((string) $testResponse->getContent())->toContain('<script nonce="'.Vite::cspNonce().'">');
});

it('lets any site frame a shared route embed, and only that', function (): void {
    $savedRoute = SavedRoute::factory()->create();

    $embed = $this->get(URL::signedRoute('routes.share.embed', $savedRoute))->assertOk();
    $share = $this->get(URL::signedRoute('routes.share', $savedRoute))->assertOk();

    expect(policyOf($embed)['frame-ancestors'])->toBe(['*'])
        ->and($embed->headers->has('X-Frame-Options'))->toBeFalse()
        ->and($embed->headers->has('Cross-Origin-Resource-Policy'))->toBeFalse()
        ->and(policyOf($share)['frame-ancestors'])->toBe(["'none'"])
        ->and($share->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('asks for HTTPS on every request of a page served over HTTPS', function (): void {
    expect(policyOf($this->get('https://localhost/login')))->toHaveKey('upgrade-insecure-requests')
        ->and(policyOf($this->get('http://localhost/login')))->not->toHaveKey('upgrade-insecure-requests');
});

it('puts no page policy on files, so the chat card map worker still reaches the tiles', function (): void {
    $script = $this->get('/mcp/apps/route-map.js')->assertOk();
    $json = $this->getJson('/.well-known/oauth-authorization-server')->assertOk();

    foreach ([$script, $json] as $response) {
        expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Strict-Transport-Security'))->toContain('max-age=63072000');
    }
});

it('lets the API reference load Scalar with the page nonce', function (): void {
    $response = $this->get('/docs')->assertOk();
    $policy = policyOf($response);

    preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", implode(' ', $policy['script-src']), $nonce);

    expect($policy['script-src'])->toContain(config('scalar.cdn'))
        ->and(substr_count((string) $response->getContent(), 'nonce="'.$nonce[1].'"'))->toBe(2);
});

it('keeps the production session cookie to traceway.online over HTTPS', function (): void {
    $env = file_get_contents(base_path('.env.production')) ?: '';

    expect($env)->toContain("SESSION_COOKIE=__Host-traceway-session\n")
        ->and($env)->toContain("SESSION_SECURE_COOKIE=true\n")
        ->and($env)->toContain("SESSION_PATH=/\n")
        ->and($env)->not->toMatch('/^SESSION_DOMAIN=\S/m');
});

it('clears the cookies that named the domain once the session is a __Host- cookie', function (): void {
    config(['session.cookie' => '__Host-traceway-session']);

    $cleared = collect($this->withUnencryptedCookie('traceway-session', 'old')->get('/login')->headers->getCookies())
        ->filter(fn ($cookie): bool => $cookie->getDomain() !== null && $cookie->isCleared())
        ->map(fn ($cookie): string => $cookie->getName())
        ->sort()
        ->values()
        ->all();

    expect($cleared)->toBe(['XSRF-TOKEN', 'traceway-session']);
});

it('clears the domain copy of a cookie the browser sends twice, such as an old Remember me', function (): void {
    config(['session.cookie' => '__Host-traceway-session']);

    $response = $this->call('GET', '/login', server: ['HTTP_COOKIE' => 'remember_web_abc=old; appearance=dark; remember_web_abc=new']);
    $cleared = collect($response->headers->getCookies())
        ->filter(fn ($cookie): bool => $cookie->getDomain() !== null && $cookie->isCleared())
        ->map(fn ($cookie): string => $cookie->getName())
        ->values()
        ->all();

    expect($cleared)->toBe(['remember_web_abc']);
});

it('leaves the cookies alone without an old session cookie', function (): void {
    config(['session.cookie' => '__Host-traceway-session']);

    $domains = collect($this->get('/login')->headers->getCookies())->map(fn ($cookie): ?string => $cookie->getDomain());

    expect($domains->filter()->all())->toBe([]);
});

it('gives the content site a policy that runs only the scripts and styles the build hashed', function (): void {
    preg_match('/map \$sent_http_content_type \$site_csp \{\s*"~\^text\/html" "([^"]+)";/', nginxConfig(), $match);
    expect($match)->toHaveKey(1);

    $policy = policyOf(new Response(headers: ['Content-Security-Policy' => $match[1]]));

    expect($policy['default-src'])->toBe(["'none'"])
        ->and($policy['script-src'])->toBe(['$site_script_hashes', "'strict-dynamic'", "'unsafe-inline'", "'self'", 'https://static.cloudflareinsights.com'])
        ->and($policy['style-src'])->toBe(["'self'", '$site_style_hashes'])
        ->and($policy['connect-src'])->toBe(["'self'", 'https://cloudflareinsights.com'])
        ->and($policy['frame-ancestors'])->toBe(["'none'"])
        ->and($policy['require-trusted-types-for'])->toBe(["'script'"])
        ->and($policy['trusted-types'])->toBe(['scripts', 'counter'])
        ->and(nginxConfig())->toContain('include /etc/nginx/nginx-csp.conf;');
})->group('gdpr');

it('copies the hashes the content site build writes next to the nginx config', function (): void {
    $dockerfile = file_get_contents(dirname(base_path(), 2).'/.docker/production/images/Dockerfile') ?: '';

    expect($dockerfile)->toContain('COPY --from=web-build /src/nginx-csp.conf /etc/nginx/nginx-csp.conf');
});

it('sends the headers from every nginx location that answers from disk', function (): void {
    preg_match_all('/\n    location ([^{]+)\{(.*?)\n    \}/s', nginxConfig(), $locations, PREG_SET_ORDER);
    expect($locations)->not->toBeEmpty();

    foreach ($locations as [, $match, $body]) {
        $fromDisk = ! str_contains($body, 'fastcgi_pass') && ! str_contains($body, 'deny all');

        expect(str_contains($body, 'include /etc/nginx/security-headers.conf;'))
            ->toBe($fromDisk, "location {$match}");
    }
});

it('puts the page policy on the planner error pages', function (): void {
    $response = $this->get('/routes/no-such-page')->assertNotFound();

    expect(policyOf($response)['script-src'])->toContain('https://challenges.cloudflare.com')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('puts no page policy on an Inertia visit, which answers with JSON', function (): void {
    $version = (string) resolve(HandleInertiaRequests::class)->version(Request::create('/'));

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get('/status')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('sends no HSTS on a local site, so DDEV keeps Mailpit on plain HTTP', function (): void {
    $this->app['env'] = 'local';

    expect($this->get('/login')->headers->has('Strict-Transport-Security'))->toBeFalse();
});
