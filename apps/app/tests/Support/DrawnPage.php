<?php

declare(strict_types=1);

namespace Tests\Support;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Draws a page from the props its controller sent, the way the server renderer does, so a test reads what the
 * rider sees and what a screen reader is told. Bun runs the page's own React code. The layouts follow app.tsx.
 */
final class DrawnPage
{
    private const string SCRIPT = <<<'TS'
        import { App } from '@inertiajs/react';
        import { createElement } from 'react';
        import { renderToString } from 'react-dom/server';

        const js = `${process.cwd()}/resources/js`;
        const page = JSON.parse(await Bun.stdin.text());
        const Page = (await import(`${js}/pages/${page.component}.tsx`)).default;
        const layouts = { auth: 'auth-layout', settings: 'settings/layout' };
        const folder = page.component.split('/')[0];
        const Layout = folder in layouts ? (await import(`${js}/layouts/${layouts[folder]}.tsx`)).default : null;
        const { TooltipProvider } = await import(`${js}/components/ui/tooltip.tsx`);

        const app = createElement(App, {
            initialPage: page,
            initialComponent: Page,
            resolveComponent: () => Page,
            defaultLayout: Layout === null ? undefined : () => Layout,
        });

        process.stdout.write(renderToString(createElement(TooltipProvider, { delayDuration: 0 }, app)));
        TS;

    /**
     * @param  TestResponse<Response>  $testResponse
     */
    public static function of(TestResponse $testResponse): HTMLDocument
    {
        $processResult = Process::path(base_path())
            ->input(json_encode($testResponse->inertiaPage(), JSON_THROW_ON_ERROR))
            ->run(['bun', '--eval', self::SCRIPT]);

        throw_if($processResult->failed(), RuntimeException::class, $processResult->errorOutput());

        return HTMLDocument::createFromString('<!doctype html><body>'.$processResult->output(), LIBXML_NOERROR);
    }
}
