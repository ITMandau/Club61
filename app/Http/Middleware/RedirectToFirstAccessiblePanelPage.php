<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin itu sendiri adalah halaman Dashboard. Role yang sengaja tidak diberi View:Dashboard
 * (mis. role yang cuma boleh melihat "Dashboard Sponsor") akan kena 403 tepat setelah login.
 * Middleware ini mengarahkan user seperti itu ke halaman panel pertama yang boleh ia buka.
 */
class RedirectToFirstAccessiblePanelPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel || ! $request->user() || trim($request->path(), '/') !== trim($panel->getPath(), '/')) {
            return $next($request);
        }

        $dashboard = collect($panel->getPages())->first(fn (string $page) => is_subclass_of($page, \Filament\Pages\Dashboard::class));
        if (! $dashboard || $dashboard::canAccess()) {
            return $next($request);
        }

        $target = self::firstAccessibleUrl();

        return $target ? redirect($target) : $next($request);
    }

    public static function firstAccessibleUrl(): ?string
    {
        $panel = Filament::getCurrentPanel();
        if (! $panel) {
            return null;
        }

        foreach ($panel->getPages() as $page) {
            if (! is_subclass_of($page, \Filament\Pages\Dashboard::class) && $page::canAccess()) {
                return $page::getUrl();
            }
        }

        foreach ($panel->getResources() as $resource) {
            if ($resource::canViewAny()) {
                return $resource::getUrl('index');
            }
        }

        return null;
    }
}
