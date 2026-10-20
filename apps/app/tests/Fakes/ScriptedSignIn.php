<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A rider coming back from a sign-in service, as the service's Socialite provider sees it: a request with the
 * service's code and the state, a session that holds the state and the PKCE verifier Traceway kept when it
 * sent the rider there, and a client that answers from a script.
 */
final readonly class ScriptedSignIn
{
    public const string STATE = 'the-state-traceway-kept-for-this-sign-in-0';

    public const string VERIFIER = 'the-verifier-traceway-kept-for-this-sign-in';

    public Request $request;

    public ScriptedGuzzle $guzzle;

    /**
     * @param  list<ResponseInterface|Throwable>  $answers  what the service answers, in turn
     * @param  array<string, string>  $query  what the service sent the rider back with
     */
    public function __construct(array $answers = [], array $query = ['code' => 'the-code', 'state' => self::STATE])
    {
        $store = new Store('traceway_session', new ArraySessionHandler(120));
        $store->put(['state' => self::STATE, 'code_verifier' => self::VERIFIER]);

        $this->request = Request::create('/auth/callback', 'GET', $query);
        $this->request->setLaravelSession($store);

        $this->guzzle = new ScriptedGuzzle($answers);
    }

    /**
     * The app's own provider for the service, built from config/services.php as a sign-in builds it.
     *
     * @template TProvider of AbstractProvider
     *
     * @param  class-string<TProvider>  $class
     * @return TProvider
     */
    public function provider(string $slug, string $class): AbstractProvider
    {
        $provider = Socialite::driver($slug);

        throw_unless($provider instanceof $class, RuntimeException::class, 'The '.$slug.' driver is not '.$class.'.');

        $provider->setRequest($this->request)->setHttpClient($this->guzzle->client());

        return $provider;
    }

    /**
     * Where the provider sends the rider, split into the address and its query.
     *
     * @return array{url: string, query: array<array-key, mixed>}
     */
    public function authorize(AbstractProvider $abstractProvider): array
    {
        $target = $abstractProvider->redirect()->getTargetUrl();
        [$url, $query] = array_pad(explode('?', $target, 2), 2, '');
        parse_str($query, $fields);

        return ['url' => $url, 'query' => $fields];
    }

    /**
     * The code challenge a PKCE provider sends for the verifier it keeps in the session.
     */
    public function challenge(): string
    {
        $verifier = $this->request->session()->get('code_verifier');

        return rtrim(strtr(base64_encode(hash('sha256', is_string($verifier) ? $verifier : '', binary: true)), '+/', '-_'), '=');
    }
}
