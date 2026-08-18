<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null && str_starts_with($routeName, 'documents.approval.')) {
            return $next($request);
        }

        if (
            in_array($routeName, ['documents.create.level', 'documents.store'], true)
            && $request->filled('revised_from')
        ) {
            return $next($request);
        }

        if ($routeName === null || ! Permission::query()->where('route', $routeName)->exists()) {
            return $next($request);
        }

        if ($request->user()?->canAccessRoute($routeName)) {
            return $next($request);
        }

        abort(403);
    }
}
