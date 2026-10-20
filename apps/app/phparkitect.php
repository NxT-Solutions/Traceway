<?php

declare(strict_types=1);

use App\Models\SavedRoute;
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\DependsOnlyOnTheseNamespaces;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsNotEnum;
use Arkitect\Expression\ForClasses\IsNotInterface;
use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\MatchOneOfTheseNames;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotHaveDependencyOutsideNamespace;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\ArchRule;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__.'/app');

    /** @var list<ArchRule> $rules */
    $rules = [];

    $rules[] = Rule::namespace('App\Domain')
        ->should(new NotHaveDependencyOutsideNamespace('App\Domain'))
        ->because('sport rules and course files stay free of HTTP, Eloquent, Saloon, and the application layers.');

    $rules[] = Rule::namespace('App\Domain')
        ->andThat(new IsNotEnum)
        ->andThat(new IsNotInterface)
        ->should(new IsFinal)
        ->because('a domain class is closed. Enums and the routing port stay as they are.');

    $rules[] = Rule::namespace('App\Commands')
        ->should(new DependsOnlyOnTheseNamespaces(['App\Commands', 'App\Account', 'App\Routes', 'App\Domain']))
        ->because('a command carries value objects. It does not touch the database, a facade, or a model.');

    $rules[] = Rule::namespace('App\Commands')
        ->should(new IsFinal)
        ->because('a command is not a base class.');

    $rules[] = Rule::namespace('App\Commands')
        ->should(new IsReadonly)
        ->because('a command does not change after it is built.');

    $rules[] = Rule::namespace('App\Commands')
        ->should(new MatchOneOfTheseNames(['*Command']))
        ->because('a command is named for the write. Results live in App\Routes.');

    $rules[] = Rule::namespace('App\Handlers')
        ->should(new IsFinal)
        ->because('one command has one handler.');

    $rules[] = Rule::namespace('App\Handlers')
        ->should(new MatchOneOfTheseNames(['*Handler']))
        ->because('a handler is the application service for one command.');

    $rules[] = Rule::namespace('App\Handlers')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Bus',
            'App\Http',
            'App\Integrations',
            'App\Mail',
            'App\Models',
            'App\Repositories',
            'Illuminate\Database\Eloquent',
            'Illuminate\Support\Facades',
            'Saloon',
        ]))
        ->because('a handler talks to contracts. Repositories persist, the mailer sends, and the session rider authorizes.');

    $rules[] = Rule::namespace('App\Queries')
        ->should(new IsFinal)
        ->because('a read model is a closed projection.');

    $rules[] = Rule::namespace('App\Queries')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Commands',
            'App\Handlers',
            'App\Http',
            'App\Models',
            'App\Integrations',
            'App\Mail',
            'Illuminate\Database\Eloquent',
            'Saloon',
        ]))
        ->because('a query uses the query builder and returns arrays or a readonly DTO.');

    $rules[] = Rule::namespace('App\Bus')
        ->should(new DependsOnlyOnTheseNamespaces(['Illuminate\Contracts\Container', 'App\Contracts\Bus']))
        ->because('the bus maps a command class to a handler class. The provider owns that map.');

    $rules[] = Rule::namespace('App\Bus')
        ->should(new IsFinal)
        ->because('there is one command bus.');

    $rules[] = Rule::namespace('App\Bus')
        ->should(new IsReadonly)
        ->because('the handler map does not change after registration.');

    $rules[] = Rule::namespace('App\Integrations')
        ->should(new DependsOnlyOnTheseNamespaces([
            'App\Domain',
            'App\Integrations',
            'Saloon',
        ]))
        ->because('an integration speaks Saloon and returns domain types.');

    $rules[] = Rule::namespace('App\Integrations')
        ->should(new IsFinal)
        ->because('a connector, request, and routing engine are closed adapters.');

    $rules[] = Rule::namespace('App\Http')
        ->should(new NotDependsOnTheseNamespaces(['App\Handlers', 'App\Mail']))
        ->because('HTTP dispatches a command or calls a query. It does not construct a handler or send mail.');

    $rules[] = Rule::namespace('App\Http\Controllers')
        ->should(new NotDependsOnTheseNamespaces([
            SavedRoute::class,
            'Illuminate\Database\Eloquent',
        ]))
        ->because('a saved route on screen comes from a query, not from the Eloquent model.');

    $rules[] = Rule::namespace('App\Http\Controllers')
        ->should(new MatchOneOfTheseNames(['*Controller']))
        ->because('an HTTP action is a controller.');

    $rules[] = Rule::namespace('App\Http\Requests')
        ->should(new MatchOneOfTheseNames(['*Request']))
        ->because('validation lives on a form request.');

    $rules[] = Rule::namespace('App\Actions')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Handlers',
            'App\Queries',
            'App\Integrations',
            'App\Mail',
            'Illuminate\Database\Eloquent',
            'Saloon',
        ]))
        ->because('Fortify actions validate and dispatch. The handler writes the row.');

    $rules[] = Rule::namespace('App\Mail')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Commands',
            'App\Handlers',
            'App\Http',
            'App\Models',
            'App\Queries',
            'App\Domain',
            'App\Integrations',
            'Saloon',
        ]))
        ->because('mail renders a Maizzle template. The handler decides when it is sent.');

    $rules[] = Rule::namespace('App\Notifications')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Commands',
            'App\Handlers',
            'App\Http',
            'App\Queries',
            'App\Integrations',
            'Saloon',
        ]))
        ->because('a notification only swaps the Fortify mail body for the Traceway template.');

    $rules[] = Rule::allClasses()
        ->except('App\Providers', 'App\Handlers')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['App\Handlers']))
        ->because('only the service provider wires a handler. Callers dispatch a command.');

    $rules[] = Rule::allClasses()
        ->except('App\Providers', 'App\Bus')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['App\Bus']))
        ->because('callers use the DispatchesCommands contract. The provider builds the concrete bus.');

    $rules[] = Rule::namespace('App\Contracts')
        ->should(new DependsOnlyOnTheseNamespaces([
            'App\Contracts',
            'App\Commands',
            'App\Account',
            'App\Routes',
        ]))
        ->because('a contract is a port. It does not know Eloquent, HTTP, or a concrete adapter.');

    $rules[] = Rule::namespace('App\Repositories')
        ->should(new IsFinal)
        ->because('an Eloquent repository is one adapter, not a base class.');

    $rules[] = Rule::namespace('App\Repositories')
        ->should(new NotDependsOnTheseNamespaces([
            'App\Handlers',
            'App\Http',
            'App\Mail',
            'App\Integrations',
            'Saloon',
        ]))
        ->because('a repository persists. It does not mail, route, or render.');

    $rules[] = Rule::allClasses()
        ->except('App\Providers', 'App\Repositories')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['App\Repositories']))
        ->because('handlers depend on the repository contract. Only the provider chooses the Eloquent adapter.');

    $rules[] = Rule::allClasses()
        ->except('App\Integrations')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['Saloon']))
        ->because('outbound HTTP is a Saloon connector under App\Integrations.');

    $rules[] = Rule::allClasses()
        ->except('App\Auth\Connections', 'App\Providers')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['Laravel\Socialite', 'SocialiteProviders', 'GuzzleHttp']))
        ->because('logging in with another service is a Socialite handshake in App\Auth\Connections. What Traceway does with a connection afterwards is a Saloon connector.');

    $rules[] = Rule::namespace('App\Auth\Connections')
        ->should(new NotDependsOnTheseNamespaces(['App\Http', 'App\Handlers', 'App\Repositories', 'Saloon']))
        ->because('the sign-in adapter returns identities. Controllers decide what to do with them.');

    $rules[] = Rule::allClasses()
        ->except('App\Models', 'App\Repositories')
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new NotDependsOnTheseNamespaces(['Illuminate\Database\Eloquent']))
        ->because('Eloquent stays on the models. A query uses the query builder. A handler uses the model.');

    $config->add($classSet, ...$rules);
};
