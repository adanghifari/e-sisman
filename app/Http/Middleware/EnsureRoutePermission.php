<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    /**
     * @param  Closure(Request): Response  $next
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

        if ($this->canAccessGeneratedDocumentRoute($request, $routeName)) {
            return $next($request);
        }

        $routeHasConfiguredPermission = collect(config('access.permissions', []))
            ->contains(fn (array $permission): bool => ($permission['route'] ?? null) === $routeName);

        if (
            $routeName === null
            || (! $routeHasConfiguredPermission && ! Permission::query()->where('route', $routeName)->exists())
        ) {
            return $next($request);
        }

        if ($request->user()?->canAccessRoute($routeName)) {
            return $next($request);
        }

        abort(403);
    }

    private function canAccessGeneratedDocumentRoute(Request $request, ?string $routeName): bool
    {
        $permissionCodes = match ($routeName) {
            'documents.master.generated.show' => $request->boolean('download')
                ? ['documents.master.download', 'documents.master.generated']
                : ['documents.master.detail', 'documents.master.generated'],
            'documents.obsolete.generated.show' => $request->boolean('download')
                ? ['documents.obsolete.download', 'documents.obsolete.generated']
                : ['documents.obsolete.detail', 'documents.obsolete.generated'],
            default => [],
        };

        return $permissionCodes !== []
            && ($request->user()?->hasAnyPermission($permissionCodes) ?? false);
    }
}
